import * as convert from './helpers/convert.js';
import Woocommerce from './woocommerce.js';
import Buckaroo from './buckaroo.js';

/**
 * Apple Pay integration.
 *
 * Detection and the button now come from Apple's official SDK
 * (apple-pay-sdk.js): the <apple-pay-button> web component renders in every
 * browser and Apple provides the cross-device QR-code handoff on non-Apple
 * devices. The Buckaroo SDK (BuckarooSdk.ApplePay) is still used to create the
 * ApplePaySession and to perform merchant validation.
 *
 * The class supports two modes:
 *   - Express (default): the Apple sheet gathers shipping/billing/contact and
 *     the order is created from that data (product/cart/top-of-checkout button).
 *   - Checkout (isOnCheckout = true): the Apple sheet only authorises payment.
 *     Shipping methods/callbacks are omitted and required contact fields are
 *     minimal, so the WooCommerce checkout form remains the source of truth for
 *     addresses. On authorisation `onAuthorized(payment)` is invoked.
 */
export default class ApplePay {
    constructor(options = {}) {
        this.buckaroo = new Buckaroo();
        this.woocommerce = new Woocommerce();
        this.store_info = this.woocommerce.getStoreInformation();
        this.selected_shipping_method = null;
        this.selected_shipping_amount = null;
        this.total_price = null;
        this.country_code = this.store_info.country_code;

        // Checkout-method behaviour (Part 2). Defaults preserve express behaviour.
        this.isOnCheckout = options.isOnCheckout === true;
        this.onAuthorized = typeof options.onAuthorized === 'function' ? options.onAuthorized : null;
        this.buttonStyle = options.buttonStyle || 'black';
        this.containerSelector = options.containerSelector || '.applepay-button-container';

        // Express renders the <apple-pay-button> web component. The standard
        // checkout method renders NO button — it is triggered from the normal
        // "Place Order" action and only authorises the payment.
        this.renderButton = options.renderButton !== false;
        this.onReady = typeof options.onReady === 'function' ? options.onReady : null;
        // Apple's <apple-pay-button> reads `locale` when other attributes change;
        // a null/missing locale makes it call ''.trim() on undefined and throw.
        // Always provide a valid locale string.
        this.locale = options.locale || (typeof navigator !== 'undefined' && navigator.language) || 'en-US';
        this.supported = false;
        this.payment = null;
    }

    /**
     * Detect Apple Pay support using Apple's official API.
     *
     * applePayCapabilities() works across browsers (and underpins the QR
     * handoff); canMakePayments() is the fallback for older WebKit.
     *
     * @returns {Promise<boolean>}
     */
    checkSupport() {
        const merchantId = this.store_info.merchant_id;

        // Apple Pay requires a secure context (HTTPS, no mixed content). Calling
        // the session/capabilities APIs on an insecure document throws
        // InvalidAccessError, so bail out gracefully (hide Apple Pay) instead of
        // letting an uncaught rejection break checkout rendering.
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

        // Only the express button renders the web component. The standard
        // checkout method has no button (driven by "Place Order").
        if (!this.renderButton) {
            return;
        }

        // Build via createElement and set attributes in a safe order. Apple's
        // <apple-pay-button> re-reads `locale` when `type` changes, so `locale`
        // must be a valid string and set BEFORE `type` (otherwise it calls
        // .trim() on null and throws "t.trim is not a function").
        const button = document.createElement('apple-pay-button');
        button.setAttribute('locale', this.locale);
        button.setAttribute('buttonstyle', this.buttonStyle);
        button.setAttribute('type', 'plain');
        button.style.width = '100%';

        // Rendered visible; init() removes it only if Apple Pay is unsupported.
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

            const cart_items = this.getItems();
            const shipping_methods = this.isOnCheckout ? [] : this.woocommerce.getShippingMethods(this.country_code);
            const first_shipping_item = this.getFirstShippingItem(shipping_methods);

            const all_items = first_shipping_item !== null ? [].concat(cart_items, first_shipping_item) : cart_items;

            const total_to_pay = this.sumTotalAmount(all_items);

            const total_item = {
                label: 'Totaal',
                amount: total_to_pay,
                type: 'final',
            };

            if (shipping_methods.length > 0) {
                this.selected_shipping_method = shipping_methods[0].identifier;
                this.selected_shipping_amount = shipping_methods[0].amount;
            }
            this.total_price = total_to_pay;

            // Express gathers full contact data from the Apple sheet; the
            // checkout method only authorises and uses the WooCommerce form.
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

            // The SDK needs a button selector; for the standard method (no
            // button) we pass the container itself — beginPayment() does not
            // require the element, it is triggered programmatically.
            const buttonSelector = this.renderButton
                ? `${this.containerSelector} apple-pay-button`
                : this.containerSelector;

            this.payment = new BuckarooSdk.ApplePay.ApplePayPayment(buttonSelector, applepay_options);

            if (this.renderButton) {
                this.injectApplePayButton();
            }

            if (this.onReady) {
                this.onReady(true);
            }
        });
    }

    /**
     * Programmatically open the Apple Pay sheet. Used by the standard checkout
     * method, which triggers payment from the normal "Place Order" action
     * (within the click user-gesture) instead of from a dedicated button.
     *
     * @param {Event} event
     * @returns {boolean} whether a session could be started
     */
    triggerPayment(event) {
        if (this.payment && typeof this.payment.beginPayment === 'function') {
            this.payment.beginPayment(event || new Event('click'));
            return true;
        }
        return false;
    }

    /**
     * Wire Apple's <apple-pay-button> web component to the Buckaroo SDK session.
     * We do not call showPayButton() (which draws the legacy styled button);
     * the official web component is the button.
     */
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
        const authorization_result = {
            status: ApplePaySession.STATUS_SUCCESS,
            errors: [],
        };

        // Checkout method: hand the authorised token back to the caller (Blocks
        // or classic checkout) which places the order through WooCommerce using
        // the checkout-form addresses. Do NOT create an order from Apple data.
        if (this.isOnCheckout) {
            if (this.onAuthorized) {
                this.onAuthorized(payment);
            }
            return Promise.resolve(authorization_result);
        }

        if (authorization_result.status === ApplePaySession.STATUS_SUCCESS) {
            this.buckaroo.createTransaction(
                payment,
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
