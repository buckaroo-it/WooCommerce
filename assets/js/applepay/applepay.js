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
/**
 * Temporary diagnostic logging for the Apple Pay checkout investigation.
 * Logs every lifecycle stage so the exact point where payment data is lost
 * can be identified on-device. Remove once the checkout flow is verified.
 */
export function bkApplePayLog(stage, details) {
    try {
        // eslint-disable-next-line no-console
        console.log(`[Buckaroo ApplePay][${stage}]`, details);
    } catch (e) {
        // logging must never break the payment flow
    }
}

/**
 * Deep-copy a value into a guaranteed-plain JS object.
 *
 * On Safari/WebKit the object handed to `onpaymentauthorized` (and the values
 * nested inside it) can be platform objects whose attributes live as accessor
 * properties on the prototype. Those are NOT "own" properties, so
 * `JSON.stringify()` on them yields `{}` — silently discarding the Apple Pay
 * token. (jQuery's form encoding walks `for...in`, which DOES see inherited
 * accessors — which is why the express product/cart flows were unaffected and
 * only the stringify-based checkout flows lost the token on Apple devices.
 * The cross-device/QR flow delivers a plain JSON object, so it worked in every
 * browser.)
 *
 * `for...in` sees both own and inherited enumerable properties, covering plain
 * objects (QR flow) and platform objects (native Safari) alike.
 */
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
            // ignore unreadable accessors
        }
    }
    return plain;
}

/**
 * Normalise the ApplePayPayment handed back on authorisation into a plain,
 * JSON-serialisable `{ token, billingContact, shippingContact }` structure.
 *
 * The known keys are read via DIRECT property access first (which also works
 * for non-enumerable accessors), then deep-copied via toPlainObject.
 */
export function normalizeApplePayPayment(payment) {
    if (!payment || typeof payment !== 'object') {
        return null;
    }

    const normalized = toPlainObject(payment);

    // Direct reads for the known shape, in case enumeration missed anything.
    try {
        if ((!normalized.token || Object.keys(normalized.token).length === 0) && payment.token) {
            normalized.token = toPlainObject(payment.token);
        }
        if (normalized.token && (!normalized.token.paymentData || Object.keys(normalized.token.paymentData).length === 0) && payment.token && payment.token.paymentData) {
            normalized.token.paymentData = toPlainObject(payment.token.paymentData);
        }
        if (!normalized.billingContact && payment.billingContact) {
            normalized.billingContact = toPlainObject(payment.billingContact);
        }
        if (!normalized.shippingContact && payment.shippingContact) {
            normalized.shippingContact = toPlainObject(payment.shippingContact);
        }
    } catch (e) {
        bkApplePayLog('normalize:error', e && e.message);
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

            this.setupPayment();

            if (this.renderButton) {
                this.injectApplePayButton();
            }

            if (this.onReady) {
                this.onReady(true);
            }
        });
    }

    /**
     * Build (or rebuild) the Buckaroo ApplePayPayment session from the current
     * cart state. Extracted from init() so the standard checkout method can
     * refresh the totals right before opening the sheet.
     */
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

        const buttonSelector = this.renderButton
            ? `${this.containerSelector} apple-pay-button`
            : this.containerSelector;

        // Stage: session (re)built.
        bkApplePayLog('setupPayment', {
            mode: this.isOnCheckout ? 'checkout' : 'express',
            selector: buttonSelector,
            total: total_to_pay,
            items: all_items.length,
        });

        this.payment = new BuckarooSdk.ApplePay.ApplePayPayment(buttonSelector, applepay_options);
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
        if (!this.payment) {
            bkApplePayLog('triggerPayment', 'No payment session available');
            return false;
        }

        if (this.isOnCheckout) {
            try {
                this.setupPayment();
            } catch (e) {
                bkApplePayLog('triggerPayment:refresh-failed', e && e.message);
                // keep the existing session if the refresh fails
            }
        }

        if (typeof this.payment.beginPayment === 'function') {
            bkApplePayLog('triggerPayment', 'Opening Apple Pay sheet (beginPayment)');
            this.payment.beginPayment(event || new Event('click'));
            return true;
        }
        bkApplePayLog('triggerPayment', 'beginPayment is not a function on the SDK payment object');
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
        // Stage: raw payment as delivered by the Buckaroo SDK / ApplePaySession.
        bkApplePayLog('authorized:raw', {
            mode: this.isOnCheckout ? 'checkout' : 'express',
            paymentType: typeof payment,
            ownKeys: payment && typeof payment === 'object' ? Object.keys(payment) : null,
            hasTokenProp: !!(payment && payment.token),
            rawStringify: (() => {
                try {
                    const s = JSON.stringify(payment);
                    return s && s.length > 200 ? `${s.slice(0, 200)}... (${s.length} chars)` : s;
                } catch (e) {
                    return `unstringifiable: ${e && e.message}`;
                }
            })(),
        });

        // Normalise to a plain object BEFORE any serialisation. On native
        // Safari the payment/token can be platform objects whose properties
        // are prototype accessors: JSON.stringify() then yields '{}' and the
        // Buckaroo API would receive an empty paymentData/token.
        const normalized = normalizeApplePayPayment(payment);
        const hasToken =
            !!normalized &&
            !!normalized.token &&
            typeof normalized.token === 'object' &&
            Object.keys(normalized.token).length > 0;

        // Stage: normalized payment about to be handed to the order flow.
        bkApplePayLog('authorized:normalized', {
            hasToken,
            tokenKeys: hasToken ? Object.keys(normalized.token) : null,
            paymentDataKeys:
                hasToken && normalized.token.paymentData && typeof normalized.token.paymentData === 'object'
                    ? Object.keys(normalized.token.paymentData)
                    : null,
            serializedLength: (() => {
                try {
                    return JSON.stringify(normalized).length;
                } catch (e) {
                    return -1;
                }
            })(),
        });

        if (!hasToken) {
            // Never let an empty token continue: fail the sheet instead of
            // sending an empty paymentData to the Buckaroo API.
            bkApplePayLog('authorized:empty-token', 'Aborting: normalized payment has no token');
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

        // Checkout method: hand the authorised token back to the caller (Blocks
        // or classic checkout) which places the order through WooCommerce using
        // the checkout-form addresses. Do NOT create an order from Apple data.
        if (this.isOnCheckout) {
            if (this.onAuthorized) {
                bkApplePayLog('checkout:onAuthorized', 'Handing normalized token to checkout handler');
                this.onAuthorized(normalized);
            }
            return Promise.resolve(authorization_result);
        }

        if (authorization_result.status === ApplePaySession.STATUS_SUCCESS) {
            bkApplePayLog('express:createTransaction', 'Sending normalized payment to create-transaction');
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
