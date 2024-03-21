jQuery(document).ready(function () {
    document.dispatchEvent(new Event("bk-jquery-loaded"));
    if (jQuery(".buckaroo-paypal-express").length) {
        BuckarooInitPaypalExpress();
    }
})

const BuckarooInitPaypalExpress = function () {
    if (jQuery === undefined) {
        console.error("Cannot initialize PaypalExpress missing jquery");
        return;
    }

    if (buckaroo_paypal_express.websiteKey.length) {

        if (buckaroo_paypal_express.merchant_id === null) {
            alert(buckaroo_paypal_express.i18n.merchant_id_required);
        }
        let buckaroo_paypal_express_class = new BuckarooPaypalExpress(
            BuckarooSdk.PayPal,
            buckaroo_paypal_express.page,
            {
                buckarooWebsiteKey: buckaroo_paypal_express.websiteKey,
                currency: buckaroo_paypal_express.currency,
                paypalMerchantId: buckaroo_paypal_express.merchant_id
            },
            buckaroo_paypal_express.ajaxurl
        )
        buckaroo_paypal_express_class.init();
    }
}

class BuckarooPaypalExpress {

    url = '/';
    /**
     * buckaroo sdk
     */
    sdk;

    result = null;

    options = {
        containerSelector: ".buckaroo-paypal-express",
        buckarooWebsiteKey: "",
        paypalMerchantId: "HHJS98P4LGHRQ",
        currency: "EUR",
        amount: 0.1,
        createPaymentHandler: this.createPaymentHandler.bind(this),
        onShippingChangeHandler: this.onShippingChangeHandler.bind(this),
        onSuccessCallback: this.onSuccessCallback.bind(this),
        onErrorCallback: this.onErrorCallback.bind(this),
        onCancelCallback: this.onCancelCallback.bind(this),
        onInitCallback: this.onInitCallback.bind(this),
        onClickCallback: this.onClickCallback.bind(this),
    }

    /**
     * current page;
     */
    page

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

        return shipping.then((response) => {
            if (response.error === false) {
                this.options.amount = response.data.value.value
                return actions.order.patch([
                    {
                        op: 'replace',
                        path: '/purchase_units/@reference_id==\'default\'/amount',
                        value: response.data.value
                    }
                ]);
            } else {
                actions.reject(response.message);
            }
        })
    }
    createPaymentHandler(data) {
        return this.createTransaction(data.orderID)
    }
    onSuccessCallback() {
        if (this.result.error === true) {
            this.displayErrorMessage(this.result.message || buckaroo_paypal_express.i18n.cannot_create_payment);
        } else {
            if(this.result.data.redirect) {
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
        this.displayErrorMessage(buckaroo_paypal_express.i18n.cancel_error_message)
    }

    onClickCallback() {
        //reset any previous payment response;
        this.result = null;
    }


    /**
     * Init class
     */
    init() {
        this.sdk.initiate(this.options);
        this.listen();
    }
    /**
     * listen to any change in the cart and get total
     */
    listen() {
        document.addEventListener("paypalExpressRefresh", () => {
            this.get_cart_total();
        })

        jQuery(".cart .quantity input").on('change', () => {
            this.get_cart_total();
        });

        jQuery(".variations_form").on("show_variation hide_variation", () => {
            this.get_cart_total();
        });
        jQuery(document.body).on('wc_fragments_refreshed updated_shipping_method', () => {
            this.get_cart_total();
            if (jQuery(".buckaroo-paypal-express").length) {
                this.sdk.initiate(this.options);
            }
        });
    }
    /**
     * Get cart total to output in paypal
     */
    get_cart_total() {
        jQuery.post(this.url, {
            action: 'buckaroo_paypal_express_get_cart_total',
            order_data: this.getOrderData(),
            page: this.page,
            cart_total_nonce: buckaroo_paypal_express.cart_total_nonce
        })
        .then((response) => {
            if (response.data) {
                this.options.amount = response.data.total
            }
        })
    }

    /**
     * Create order and do payment
     * @param {string} orderId 
     * @returns Promise
     */
    createTransaction(orderId) {
        return new Promise((resolve, reject) => {
            jQuery.post(this.url, {
                action: 'buckaroo_paypal_express_order',
                orderId,
                send_order_nonce: buckaroo_paypal_express.send_order_nonce
            }).then((response) => {
                    this.result = response;
                    resolve(response);
            }, (reason) => reject(reason))
        })
    }


    /**
     * Set shipping on cart and return new total
     * @param {Object} data 
     * @returns 
     */
    setShipping(data) {
        return jQuery.post(
            this.url,
            {
                action: 'buckaroo_paypal_express_set_shipping',
                shipping_data: data,
                order_data: this.getOrderData(),
                page: this.page,
                set_shipping_nonce: buckaroo_paypal_express.set_shipping_nonce
            }
        )
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
                    name: "add-to-cart",
                    value: productIdField.val()
                })
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
