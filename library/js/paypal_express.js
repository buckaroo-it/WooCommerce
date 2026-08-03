jQuery(document).ready(function () {
    document.dispatchEvent(new Event('bk-jquery-loaded'));
    if (jQuery('.buckaroo-paypal-express').length) {
        BuckarooInitPaypalExpress();
    }
});

/** Kept in sync with the express button height in buckaroo-custom.css. */
const BUCKAROO_EXPRESS_BUTTON_HEIGHT = 40;

/**
 * Force PayPal to the shared express button height.
 *
 * Left alone it steps its height off the container width (35/45/55px) and can
 * never match the other buttons. It does honour an explicit style.height, but the
 * SDK builds its paypal.Buttons() options internally and forwards no style.
 */
const buckarooWrapPaypalButtons = function (namespace) {
    try {
        const original = namespace.Buttons;

        if (typeof original !== 'function' || original.buckarooHeightPatched === true) {
            return;
        }

        const patched = function (options) {
            return original(
                Object.assign({}, options, {
                    style: Object.assign({}, options && options.style, {
                        height: BUCKAROO_EXPRESS_BUTTON_HEIGHT,
                    }),
                })
            );
        };

        Object.keys(original).forEach(key => {
            patched[key] = original[key];
        });
        patched.buckarooHeightPatched = true;

        namespace.Buttons = patched;
    } catch (e) {
        // PayPal keeps its own height.
    }
};

/**
 * Run an SDK initiate() with the button factory wrapped.
 *
 * The wrapper must land between PayPal defining window.paypal and the SDK
 * rendering from a load listener on the script it injects. Two simpler hooks do
 * not work: PayPal redefines window.paypal, discarding any accessor put there
 * first, and script load events never reach a capture listener on window. So
 * shadow document.createElement for the synchronous part of initiate() and get a
 * load listener onto that script before the SDK's, which then runs first.
 *
 * Any failure leaves PayPal on its own height, which the CSS still contains.
 */
const buckarooInitiateWithPaypalHeight = function (initiate) {
    const createElement = document.createElement;

    try {
        document.createElement = function (tagName) {
            const element = createElement.apply(document, arguments);

            if (String(tagName).toLowerCase() === 'script') {
                element.addEventListener('load', function () {
                    if (window.paypal) {
                        buckarooWrapPaypalButtons(window.paypal);
                    }
                });
            }

            return element;
        };

        // A later initiate() reuses the existing namespace, with no script load.
        if (window.paypal) {
            buckarooWrapPaypalButtons(window.paypal);
        }

        initiate();
    } finally {
        document.createElement = createElement;
    }
};

const BuckarooInitPaypalExpress = function () {
    if (jQuery === undefined) {
        console.error('Cannot initialize PaypalExpress missing jquery');
        return;
    }

    if (buckaroo_paypal_express.websiteKey.length) {
        if (buckaroo_paypal_express.merchant_id === null) {
            alert(buckaroo_paypal_express.i18n.merchant_id_required);
        }

        var isTestMode = !!buckaroo_paypal_express.is_test;

        // Signal the environment to the SDK; it then selects the matching
        // (sandbox/live) PayPal client id internally.
        if (BuckarooSdk && BuckarooSdk.Base && typeof BuckarooSdk.Base.setTestMode === 'function') {
            BuckarooSdk.Base.setTestMode(isTestMode);
        }

        let buckaroo_paypal_express_class = new BuckarooPaypalExpress(
            BuckarooSdk.PayPal,
            buckaroo_paypal_express.page,
            {
                buckarooWebsiteKey: buckaroo_paypal_express.websiteKey,
                currency: buckaroo_paypal_express.currency,
                paypalMerchantId: buckaroo_paypal_express.merchant_id,
                isTestMode: isTestMode,
            },
            buckaroo_paypal_express.ajaxurl
        );
        buckaroo_paypal_express_class.init();
    }
};

class BuckarooPaypalExpress {
    url = '/';
    /**
     * buckaroo sdk
     */
    sdk;

    result = null;

    options = {
        containerSelector: '.buckaroo-paypal-express',
        buckarooWebsiteKey: '',
        paypalMerchantId: 'HHJS98P4LGHRQ',
        currency: 'EUR',
        amount: 0.1,
        createPaymentHandler: this.createPaymentHandler.bind(this),
        onShippingChangeHandler: this.onShippingChangeHandler.bind(this),
        onSuccessCallback: this.onSuccessCallback.bind(this),
        onErrorCallback: this.onErrorCallback.bind(this),
        onCancelCallback: this.onCancelCallback.bind(this),
        onInitCallback: this.onInitCallback.bind(this),
        onClickCallback: this.onClickCallback.bind(this),
    };

    /**
     * current page;
     */
    page;

    constructor(sdk, page, options, url) {
        this.sdk = sdk;
        this.page = page;
        this.url = url;
        this.options = Object.assign(this.options, options);
    }
    /**
     * Api events
     */
    onShippingChangeHandler(data, actions) {
        let shipping = this.setShipping(data);

        return shipping.then(response => {
            if (response.error === false) {
                this.options.amount = response.data.value.value;
                return actions.order.patch([
                    {
                        op: 'replace',
                        path: "/purchase_units/@reference_id=='default'/amount",
                        value: response.data.value,
                    },
                ]);
            } else {
                actions.reject(response.message);
            }
        });
    }
    createPaymentHandler(data) {
        return this.createTransaction(data.orderID);
    }
    onSuccessCallback() {
        if (this.result.error === true) {
            this.displayErrorMessage(this.result.message || buckaroo_paypal_express.i18n.cannot_create_payment);
        } else {
            if (this.result.data.redirect) {
                window.location = this.result.data.redirect;
            } else {
                this.displayErrorMessage(buckaroo_paypal_express.i18n.cannot_create_payment);
            }
        }
    }

    onErrorCallback(reason) {
        // custom error behavior
        this.displayErrorMessage(reason);
    }
    onInitCallback() {
        this.get_cart_total();
    }
    onCancelCallback() {
        this.displayErrorMessage(buckaroo_paypal_express.i18n.cancel_error_message);
    }

    onClickCallback() {
        //reset any previous payment response;
        this.result = null;
    }

    /**
     * Init class
     */
    init() {
        this.initiate();
        this.listen();
    }
    /**
     * Render the button at the shared express height.
     */
    initiate() {
        buckarooInitiateWithPaypalHeight(() => this.sdk.initiate(this.options));
    }
    /**
     * listen to any change in the cart and get total
     */
    listen() {
        document.addEventListener('paypalExpressRefresh', () => {
            this.get_cart_total();
        });

        jQuery('.cart .quantity input').on('change', () => {
            this.get_cart_total();
        });

        jQuery('.variations_form').on('show_variation hide_variation', () => {
            this.get_cart_total();
        });
        jQuery(document.body).on('wc_fragments_refreshed updated_shipping_method', () => {
            this.get_cart_total();
            if (jQuery('.buckaroo-paypal-express').length) {
                this.initiate();
            }
        });
    }
    /**
     * Get cart total to output in paypal
     */
    get_cart_total() {
        jQuery
            .post(this.url, {
                action: 'buckaroo_paypal_express_get_cart_total',
                order_data: this.getOrderData(),
                page: this.page,
                cart_total_nonce: buckaroo_paypal_express.cart_total_nonce,
            })
            .then(response => {
                if (response.data) {
                    this.options.amount = response.data.total;
                }
            });
    }

    /**
     * Create order and do payment
     * @param {string} orderId
     * @returns Promise
     */
    createTransaction(orderId) {
        return new Promise((resolve, reject) => {
            jQuery
                .post(this.url, {
                    action: 'buckaroo_paypal_express_order',
                    orderId,
                    send_order_nonce: buckaroo_paypal_express.send_order_nonce,
                })
                .then(
                    response => {
                        this.result = response;
                        resolve(response);
                    },
                    reason => reject(reason)
                );
        });
    }

    /**
     * Set shipping on cart and return new total
     * @param {Object} data
     * @returns
     */
    setShipping(data) {
        return jQuery.post(this.url, {
            action: 'buckaroo_paypal_express_set_shipping',
            shipping_data: data,
            order_data: this.getOrderData(),
            page: this.page,
            set_shipping_nonce: buckaroo_paypal_express.set_shipping_nonce,
        });
    }
    /**
     * Get form data for product page to create cart
     * @returns
     */
    getOrderData() {
        let form = jQuery('.cart');
        let orderData = null;
        if (this.page === 'product') {
            orderData = form.serializeArray();

            let productIdField = form.find('[name="add-to-cart"]');

            if (productIdField.length) {
                orderData.push({
                    name: 'add-to-cart',
                    value: productIdField.val(),
                });
            }
        }
        return orderData;
    }
    /**
     * Display any validation errors we receive
     * @param {string} message
     */
    displayErrorMessage(message) {
        jQuery('.buckaroo-paypal-express-error').remove();
        if (typeof message === 'object') {
            console.log(message);
            message = buckaroo_paypal_express.i18n.cannot_create_payment;
        }
        const content = `      
        <div class="woocommerce-error buckaroo-paypal-express-error" role="alert">
          ${message}
        </div>
      `;
        jQuery('.woocommerce-notices-wrapper').first().prepend(content);
        setTimeout(function () {
            jQuery('.buckaroo-paypal-express-error').fadeOut(1000);
        }, 10000);
    }
}
