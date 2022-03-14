jQuery(document).ready(function ($) {
    if ($(".buckaroo-paypal-express").length && buckaroo_paypal_express.websiteKey.length) {
        let buckaroo_paypal_express_class = new BuckarooPaypalExpress(
            BuckarooSdk.PayPal,
            buckaroo_paypal_express.page,
            {
                buckarooWebsiteKey: buckaroo_paypal_express.websiteKey,
                currency: buckaroo_paypal_express.currency
            },
            buckaroo_paypal_express.ajaxurl
        )
        buckaroo_paypal_express_class.init();
    }
})

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
        console.log(this.result);
        if (this.result.error === true) {
            this.displayErrorMessage(message);
        } else {
            if(this.result.data.redirect) {
                window.location = this.result.data.redirect;
            } else {
                this.displayErrorMessage(buckaroo_paypal_express.i18n.cannot_create_payment);
            }
        }
        console.log('onSuccessCallback');
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
        $(".cart .quantity input").on('change', () => {
            this.get_cart_total();
        });

        $(".variations_form").on("show_variation hide_variation", () => {
            this.get_cart_total();
        });
        $(document.body).on('wc_fragments_refreshed updated_shipping_method', () => {
            this.get_cart_total();
            if ($(".buckaroo-paypal-express").length) {
                this.sdk.initiate(this.options);
            }
        });
    }
    /**
     * Get cart total to output in paypal
     */
    get_cart_total() {
        $.post(this.url, {
            action: 'buckaroo_paypal_express_get_cart_total',
            order_data: this.getOrderData(),
            page: this.page
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
            $.post(this.url, { action: 'buckaroo_paypal_express_order', orderId })
                .then((response) => {
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
        return $.post(
            this.url,
            {
                action: 'buckaroo_paypal_express_set_shipping',
                shipping_data: data,
                order_data: this.getOrderData(),
                page: this.page
            }
        )
    }
    /**
     * Get form data for product page to create cart
     * @returns 
     */
    getOrderData() {
        let form = $('.cart');
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
        $('.buckaroo-paypal-express-error').remove();
        if (typeof message === 'object') {
            console.log(message);
            message = buckaroo_paypal_express.i18n.cannot_create_payment;
        }
        const content = `      
        <div class="woocommerce-error buckaroo-paypal-express-error" role="alert">
          ${message}
        </div>
      `;
        $('.woocommerce-notices-wrapper').first().prepend(content);
        setTimeout(function () {
            $('.buckaroo-paypal-express-error').fadeOut(1000);
        }, 10000);
    }


}
