import * as convert from './helpers/convert.js';
import Woocommerce from './woocommerce.js';
import Buckaroo from './buckaroo.js';

export function toPlainObject(value, depth = 0) {
    if (value === null || value === undefined || typeof value !== 'object' || depth > 8) {
        return value;
    }
    if (Array.isArray(value)) {
        return value.map(item => toPlainObject(item, depth + 1));
    }

    const plain = {};
    // eslint-disable-next-line no-restricted-syntax, guard-for-in
    for (const key in value) {
        try {
            const item = value[key];
            if (typeof item !== 'function') {
                plain[key] = toPlainObject(item, depth + 1);
            }
        } catch (e) {
            // ignore
        }
    }
    return plain;
}

export function normalizeApplePayPayment(payment) {
    if (!payment || typeof payment !== 'object') {
        return null;
    }

    const normalized = toPlainObject(payment);

    try {
        if ((!normalized.token || Object.keys(normalized.token).length === 0) && payment.token) {
            normalized.token = toPlainObject(payment.token);
        }
        if (
            normalized.token &&
            (!normalized.token.paymentData || Object.keys(normalized.token.paymentData).length === 0) &&
            payment.token &&
            payment.token.paymentData
        ) {
            normalized.token.paymentData = toPlainObject(payment.token.paymentData);
        }
        if (!normalized.billingContact && payment.billingContact) {
            normalized.billingContact = toPlainObject(payment.billingContact);
        }
        if (!normalized.shippingContact && payment.shippingContact) {
            normalized.shippingContact = toPlainObject(payment.shippingContact);
        }
    } catch (e) {
        // ignore
    }

    return normalized;
}

export default class ApplePay {
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
        this.buttonStyle = options.buttonStyle || 'black';
        this.containerSelector = options.containerSelector || '.applepay-button-container';
        this.renderButton = options.renderButton !== false;
        this.onReady = typeof options.onReady === 'function' ? options.onReady : null;
        this.locale = options.locale || (typeof navigator !== 'undefined' && navigator.language) || 'en-US';
        this.supported = false;
        this.payment = null;
    }

    checkSupport() {
        const merchantId = this.store_info.merchant_id;

        if (typeof window.ApplePaySession === 'undefined' || window.isSecureContext === false) {
            return Promise.resolve(false);
        }

        const safeCanMakePayments = () => {
            try {
                return ApplePaySession.canMakePayments() === true;
            } catch (e) {
                return false;
            }
        };

        try {
            if (typeof ApplePaySession.applePayCapabilities === 'function') {
                return Promise.resolve(ApplePaySession.applePayCapabilities(merchantId))
                    .then(caps => !!caps && caps.paymentCredentialStatus !== 'applePayUnsupported')
                    .catch(() => safeCanMakePayments());
            }

            return Promise.resolve(safeCanMakePayments());
        } catch (e) {
            return Promise.resolve(false);
        }
    }

    rebuild() {
        const container = jQuery(this.containerSelector);
        container.find('apple-pay-button').remove();
        container.find('div').remove();

        if (!this.renderButton) {
            return;
        }

        const button = document.createElement('apple-pay-button');
        button.setAttribute('locale', this.locale);
        button.setAttribute('buttonstyle', this.buttonStyle);
        button.setAttribute('type', 'plain');
        button.style.width = '100%';

        container.append(button);
    }

    init() {
        this.checkSupport().then(is_applepay_supported => {
            this.supported = is_applepay_supported === true;

            if (!is_applepay_supported) {
                jQuery(this.containerSelector).find('apple-pay-button').remove();
                if (this.onReady) {
                    this.onReady(false);
                }
                return;
            }

            try {
                this.setupPayment();
            } catch (error) {
                this.woocommerce.displayErrorMessage(error.message || 'Unable to initialize Apple Pay.');
                jQuery(this.containerSelector).find('apple-pay-button').remove();
                if (this.onReady) {
                    this.onReady(false);
                }
                return;
            }

            if (this.renderButton) {
                this.injectApplePayButton();
            }

            if (this.onReady) {
                this.onReady(true);
            }
        });
    }

    setupPayment() {
        const cart_items = this.getItems();
        let shipping_methods = [];
        let all_items = cart_items;
        let total_to_pay;

        if (this.isOnCheckout) {
            const cart_total = this.woocommerce.getCartTotal();

            if (cart_total && cart_total.shipping > 0) {
                all_items = [].concat(cart_items, {
                    type: 'final',
                    label: convert.maxCharacters(cart_total.shipping_label || 'Shipping', 25),
                    amount: convert.toDecimal(cart_total.shipping),
                    qty: 1,
                });
            }

            total_to_pay =
                cart_total && typeof cart_total.total === 'number' && cart_total.total > 0
                    ? convert.toDecimal(cart_total.total)
                    : this.sumTotalAmount(all_items);
        } else {
            shipping_methods = this.woocommerce.getShippingMethods(this.country_code);
            const first_shipping_item = this.getFirstShippingItem(shipping_methods);

            all_items = first_shipping_item !== null ? [].concat(cart_items, first_shipping_item) : cart_items;
            total_to_pay = this.sumTotalAmount(all_items);

            if (shipping_methods.length > 0) {
                this.selected_shipping_method = shipping_methods[0].identifier;
                this.selected_shipping_amount = shipping_methods[0].amount;
            }
        }

        this.total_price = total_to_pay;

        const total_item = {
            label: 'Totaal',
            amount: total_to_pay,
            type: 'final',
        };

        const requiredContactFields = this.isOnCheckout ? [] : ['name', 'email', 'postalAddress', 'phone'];
        const shippingMethodsCallback = this.isOnCheckout ? null : this.processShippingMethodsCallback.bind(this);
        const changeContactCallback = this.isOnCheckout ? null : this.processChangeContactInfoCallback.bind(this);

        const applepay_options = new BuckarooSdk.ApplePay.ApplePayOptions(
            this.store_info.store_name,
            this.store_info.country_code,
            this.store_info.currency_code,
            this.store_info.culture_code,
            this.store_info.merchant_id,
            all_items,
            total_item,
            'shipping',
            shipping_methods,
            this.processApplepayCallback.bind(this),
            shippingMethodsCallback,
            changeContactCallback,
            requiredContactFields,
            requiredContactFields
        );

        const buttonSelector = this.renderButton
            ? `${this.containerSelector} apple-pay-button`
            : this.containerSelector;

        this.payment = new BuckarooSdk.ApplePay.ApplePayPayment(buttonSelector, applepay_options);
    }

    triggerPayment(event) {
        if (!this.payment) {
            return false;
        }

        if (this.isOnCheckout) {
            try {
                this.setupPayment();
            } catch (e) {
                return false;
            }
        }

        if (typeof this.payment.beginPayment === 'function') {
            this.payment.beginPayment(event || new Event('click'));
            return true;
        }
        return false;
    }

    injectApplePayButton() {
        const button = jQuery(this.containerSelector).find('apple-pay-button')[0];
        if (!button || !this.payment) {
            return;
        }

        const paymentRef = this.payment;
        button.addEventListener('click', event => {
            event.stopPropagation();
            paymentRef.beginPayment(event);
        });

        button.style.display = '';
    }

    processChangeContactInfoCallback(contact_info) {
        this.country_code = contact_info.countryCode;

        const cart_items = this.getItems();
        const shipping_methods = this.woocommerce.getShippingMethods(this.country_code);
        const first_shipping_item = this.getFirstShippingItem(shipping_methods);

        const all_items = first_shipping_item !== null ? [].concat(cart_items, first_shipping_item) : cart_items;

        const total_to_pay = this.sumTotalAmount(all_items);

        const total_item = {
            label: 'Totaal',
            amount: total_to_pay,
            type: 'final',
        };

        const info = {
            newShippingMethods: shipping_methods,
            newTotal: total_item,
            newLineItems: all_items,
        };

        if (shipping_methods.length > 0) {
            var errors = {};
            this.selected_shipping_method = shipping_methods[0].identifier;
            this.selected_shipping_amount = shipping_methods[0].amount;
        } else {
            var errors = this.shippingCountryError(contact_info);
        }

        this.total_price = total_to_pay;

        return Promise.resolve(Object.assign(info, errors));
    }

    processShippingMethodsCallback(selected_method) {
        const cart_items = this.getItems();
        const shipping_item = {
            type: 'final',
            label: selected_method.label,
            amount: convert.toDecimal(selected_method.amount) || 0,
            qty: 1,
        };

        const all_items = [].concat(cart_items, shipping_item);
        const total_to_pay = this.sumTotalAmount(all_items);

        const total_item = {
            label: 'Totaal',
            amount: total_to_pay,
            type: 'final',
        };

        this.selected_shipping_method = selected_method.identifier;
        this.selected_shipping_amount = selected_method.amount;
        this.total_price = total_to_pay;

        return Promise.resolve({
            status: ApplePaySession.STATUS_SUCCESS,
            newTotal: total_item,
            newLineItems: all_items,
        });
    }

    processApplepayCallback(payment) {
        const normalized = normalizeApplePayPayment(payment);
        const hasToken =
            !!normalized &&
            !!normalized.token &&
            typeof normalized.token === 'object' &&
            Object.keys(normalized.token).length > 0;

        if (!hasToken) {
            this.woocommerce.displayErrorMessage(
                'Your payment could not be processed: the Apple Pay token was not received. Please try again.'
            );
            return Promise.resolve({
                status: ApplePaySession.STATUS_FAILURE,
                errors: [],
            });
        }

        const authorization_result = {
            status: ApplePaySession.STATUS_SUCCESS,
            errors: [],
        };

        if (this.isOnCheckout) {
            if (this.onAuthorized) {
                this.onAuthorized(normalized);
            }
            return Promise.resolve(authorization_result);
        }

        if (authorization_result.status === ApplePaySession.STATUS_SUCCESS) {
            this.buckaroo.createTransaction(
                normalized,
                this.total_price,
                this.selected_shipping_method,
                this.woocommerce.getItems(this.country_code)
            );
        } else {
            const errors = authorization_result.errors.map(error => error.message).join(' ');

            this.woocommerce.displayErrorMessage(`Your payment could not be processed. ${errors}`);
        }

        return Promise.resolve(authorization_result);
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

    shippingCountryError(contact_info) {
        return {
            errors: [
                new ApplePayError(
                    'shippingContactInvalid',
                    'country',
                    'Shipping is not available for the selected country'
                ),
            ],
        };
    }
}
