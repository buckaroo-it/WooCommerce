import * as convert from './helpers/convert.js';
import Woocommerce from './woocommerce.js';
import Buckaroo from './buckaroo.js';

export default class GooglePay {
    constructor(options = {}) {
        this.buckaroo = new Buckaroo();
        this.woocommerce = new Woocommerce();
        this.store_info = this.woocommerce.getStoreInformation();
        this.selected_shipping_method = null;
        this.selected_shipping_amount = null;
        this.total_price = null;
        this.country_code = this.store_info.country_code;
        this.isOnCheckout = options.isOnCheckout === true;
        this.onAuthorized = typeof options.onAuthorized === 'function' ? options.onAuthorized : null;
        this.containerSelector = options.containerSelector || '.googlepay-button-container';
        this.buttonElementId = options.buttonElementId || 'googlepay-button-element';
        this.payment = null;
    }

    rebuild() {
        const existing = document.getElementById(this.buttonElementId);
        if (existing) {
            while (existing.firstChild) {
                existing.removeChild(existing.firstChild);
            }
        } else {
            jQuery(`${this.containerSelector} div`).remove();
            jQuery(this.containerSelector).append(`<div id="${this.buttonElementId}">`);
        }

        // The Buckaroo SDK always renders the branded Google Pay button into the
        // holder. In checkout mode the flow is driven by the WooCommerce "Place
        // order" action instead, so the holder stays hidden.
        if (this.isOnCheckout) {
            jQuery(`#${this.buttonElementId}`).hide();
        }
    }

    init() {
        this.setupPayment();

        this.payment.initiate();
    }

    setupPayment() {
        const cart_items = this.getItems();
        // In checkout mode the Google Pay sheet only authorises the payment: the
        // shopper's name, email and addresses come from the WooCommerce checkout
        // form, and the amount from the cart total WooCommerce already computed.
        const shipping_methods = this.isOnCheckout ? [] : this.woocommerce.getShippingMethods(this.country_code);
        let all_items = cart_items;
        let total_to_pay;

        if (this.isOnCheckout) {
            const cart_total = this.woocommerce.getCartTotal();

            total_to_pay =
                cart_total && typeof cart_total.total === 'number' && cart_total.total > 0
                    ? convert.toDecimal(cart_total.total)
                    : this.sumTotalAmount(all_items);
        } else {
            const first_shipping_item = this.getFirstShippingItem(shipping_methods);

            all_items = first_shipping_item !== null ? [].concat(cart_items, first_shipping_item) : cart_items;
            total_to_pay = this.sumTotalAmount(all_items);
        }

        if (shipping_methods.length > 0) {
            this.selected_shipping_method = shipping_methods[0].identifier;
            this.selected_shipping_amount = shipping_methods[0].amount;
        }
        this.total_price = total_to_pay;

        const environment = this.store_info.mode === 'live' ? 'PRODUCTION' : 'TEST';
        const buttonStyle = this.store_info.button_style || 'black';
        const hasShipping = shipping_methods && shipping_methods.length > 0;

        const shippingOptions = hasShipping
            ? shipping_methods.map(method => ({
                  id: method.identifier,
                  label: method.label,
                  description: '',
              }))
            : [];

        const options = {
            environment: environment,
            buttonColor: buttonStyle === 'white' ? 'white' : 'black',
            buttonType: 'pay',
            buttonSizeMode: 'fill',
            buttonContainerId: this.buttonElementId,
            buttonLocale: this.store_info.locale || 'en',
            totalPriceStatus: this.isOnCheckout ? 'FINAL' : 'ESTIMATED',
            totalPrice: String(total_to_pay),
            currencyCode: this.store_info.currency_code,
            countryCode: this.store_info.country_code,
            merchantName: this.store_info.store_name,
            merchantId: this.store_info.google_merchant_id || '',
            merchantOrigin: window.location.hostname,
            gatewayMerchantId: this.store_info.merchant_id,
            shippingAddressRequired: !this.isOnCheckout,
            shippingOptionRequired: hasShipping,
            onGooglePayLoadError: error => {
                console.error('Error loading GooglePay:', error);
            },
            processPayment: paymentData => {
                return this.processGooglepayCallback(paymentData);
            },
        };

        if (hasShipping) {
            options.shippingOptionParameters = {
                shippingOptions: shippingOptions,
                defaultSelectedOptionId: shippingOptions[0].id,
            };
            options.onPaymentDataChanged = intermediatePaymentData => {
                return this.onPaymentDataChanged(intermediatePaymentData);
            };
        }

        this.payment = new BuckarooSdk.GooglePay.GooglePayPayment(options);

        // Only the Express Checkout button has to collect the shopper's details
        // from the Google Pay sheet; in checkout mode they come from the form.
        if (!this.isOnCheckout) {
            this.requireContactDetails(this.payment);
        }
    }

    /**
     * Make the Google Pay sheet return the shopper's email and billing address.
     *
     * The Buckaroo SDK assembles the payment data request itself and carries over
     * the shipping options only, so passing emailRequired or billingAddressRequired
     * as an SDK option has no effect: the sheet never asks for them and the express
     * order is created without a billing address and without an email address.
     * Wrapping the request builder puts both back where Google expects them —
     * the billing flags belong to the card payment method, not the request root.
     *
     * @param {object} payment the SDK payment instance to wrap.
     */
    requireContactDetails(payment) {
        if (!payment || typeof payment.getBaseRequest !== 'function') {
            return;
        }

        const buildRequest = payment.getBaseRequest.bind(payment);

        payment.getBaseRequest = () => {
            const request = buildRequest();

            return {
                ...request,
                emailRequired: true,
                allowedPaymentMethods: (request.allowedPaymentMethods || []).map(method => ({
                    ...method,
                    parameters: {
                        ...method.parameters,
                        billingAddressRequired: true,
                        billingAddressParameters: {
                            format: 'FULL',
                            phoneNumberRequired: true,
                        },
                    },
                })),
            };
        };
    }

    /**
     * Open the Google Pay sheet without the branded button, for the standard
     * checkout method driven by the WooCommerce "Place order" action. Must be
     * called from a user gesture — Google Pay rejects loadPaymentData otherwise.
     *
     * @returns {boolean} whether the sheet could be opened.
     */
    triggerPayment() {
        // Refresh the amount so a cart change made after the instance was built
        // (shipping method, coupon) is reflected in the sheet.
        try {
            this.setupPayment();
        } catch (e) {
            // keep the existing session if the refresh fails
        }

        if (!this.payment || typeof this.payment.onGooglePaymentButtonClicked !== 'function') {
            return false;
        }

        try {
            // Throws when pay.js has not finished loading yet, so the caller can
            // surface a message instead of leaving the click without any effect.
            this.payment.onGooglePaymentButtonClicked();
        } catch (e) {
            return false;
        }

        return true;
    }

    onPaymentDataChanged(intermediatePaymentData) {
        try {
            const countryCode = intermediatePaymentData.shippingAddress?.countryCode || this.country_code;
            this.country_code = countryCode;

            const shippingMethods = this.woocommerce.getShippingMethods(countryCode);
            const cartItems = this.getItems();

            if (!shippingMethods || !Array.isArray(shippingMethods) || shippingMethods.length === 0) {
                return Promise.resolve({
                    newTransactionInfo: {
                        totalPriceStatus: 'FINAL',
                        totalPrice: String(this.total_price),
                        currencyCode: this.store_info.currency_code,
                        countryCode: this.store_info.country_code,
                    },
                    error: {
                        reason: 'SHIPPING_ADDRESS_UNSERVICEABLE',
                        message: 'Cannot ship to this address',
                        intent: 'SHIPPING_ADDRESS',
                    },
                });
            }

            const shippingOptions = shippingMethods.map(method => ({
                id: method.identifier,
                label: method.label,
                description: '',
            }));

            if (intermediatePaymentData.shippingOptionData?.id) {
                const selectedMethod = shippingMethods.find(
                    m => m.identifier === intermediatePaymentData.shippingOptionData.id
                );
                if (selectedMethod) {
                    this.selected_shipping_method = selectedMethod.identifier;
                    this.selected_shipping_amount = selectedMethod.amount;
                }
            } else if (shippingMethods.length > 0) {
                this.selected_shipping_method = shippingMethods[0].identifier;
                this.selected_shipping_amount = shippingMethods[0].amount;
            }

            const shippingCost = this.selected_shipping_amount || 0;
            const itemsTotal = cartItems.reduce((sum, item) => sum + item.amount, 0);
            const totalPrice = convert.toDecimal(itemsTotal + shippingCost);
            this.total_price = totalPrice;

            return Promise.resolve({
                newTransactionInfo: {
                    totalPriceStatus: 'FINAL',
                    totalPrice: String(totalPrice),
                    currencyCode: this.store_info.currency_code,
                    countryCode: this.store_info.country_code,
                },
                newShippingOptionParameters: {
                    shippingOptions: shippingOptions,
                    defaultSelectedOptionId: this.selected_shipping_method || shippingOptions[0].id,
                },
            });
        } catch (error) {
            console.error('Error in onPaymentDataChanged:', error);
            return Promise.resolve({
                newTransactionInfo: {
                    totalPriceStatus: 'FINAL',
                    totalPrice: String(this.total_price),
                    currencyCode: this.store_info.currency_code,
                    countryCode: this.store_info.country_code,
                },
                error: {
                    reason: 'SHIPPING_ADDRESS_UNSERVICEABLE',
                    message: 'Cannot ship to this address',
                    intent: 'SHIPPING_ADDRESS',
                },
            });
        }
    }

    processGooglepayCallback(paymentData) {
        const email = paymentData.email || '';
        const googleBilling = paymentData.paymentMethodData.info?.billingAddress || {};
        const googleShipping = paymentData.shippingAddress || {};

        // Google returns only the addresses the sheet was told to collect, so use
        // whichever one came back for both. Without this the order can end up
        // without a billing address, which breaks bookkeeping integrations.
        const billingAddress = Object.keys(googleBilling).length > 0 ? googleBilling : googleShipping;
        const shippingAddress = Object.keys(googleShipping).length > 0 ? googleShipping : googleBilling;

        const payment = {
            token: paymentData.paymentMethodData.tokenizationData.token,
            billingContact: this.mapGoogleContact(billingAddress, email),
            shippingContact: this.mapGoogleContact(shippingAddress, email),
        };

        // Checkout mode only authorises: the order is created by the regular
        // WooCommerce submission, which carries the token along.
        if (this.isOnCheckout) {
            if (this.onAuthorized) {
                this.onAuthorized(payment);
            }

            return Promise.resolve({ success: true });
        }

        this.buckaroo.createTransaction(
            payment,
            this.total_price,
            this.selected_shipping_method,
            this.woocommerce.getItems(this.country_code)
        );

        return Promise.resolve({});
    }

    mapGoogleContact(address, email) {
        // A FULL format Google address splits the street over three lines.
        const lines = [address.address1 || '', address.address2 || '', address.address3 || ''].filter(Boolean);
        return {
            givenName: address.name ? address.name.split(' ')[0] : '',
            familyName: address.name ? address.name.split(' ').slice(1).join(' ') : '',
            emailAddress: email || '',
            phoneNumber: address.phoneNumber || '',
            addressLines: lines.length > 0 ? lines : [''],
            locality: address.locality || '',
            administrativeArea: address.administrativeArea || '',
            postalCode: address.postalCode || '',
            countryCode: address.countryCode || '',
        };
    }

    sumTotalAmount(items) {
        const total = items.reduce((a, b) => a + b.amount, 0);

        return convert.toDecimal(total);
    }

    getFirstShippingItem(shipping_methods) {
        if (shipping_methods.length > 0) {
            return {
                type: 'final',
                label: shipping_methods[0].label,
                amount: shipping_methods[0].amount || 0,
                qty: 1,
            };
        }
        return null;
    }

    getItems() {
        return this.woocommerce.getItems(this.country_code).map(item => {
            const label = `${item.quantity} x ${item.name}`;
            return {
                type: 'final',
                label: convert.maxCharacters(label, 25),
                amount: convert.toDecimal(item.price),
                qty: item.quantity,
            };
        });
    }
}
