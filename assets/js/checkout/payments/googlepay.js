class BuckarooGooglePayCheckout {
    constructor() {
        this.instance = null;
        this.token = null;
        this.methodId = 'buckaroo_googlepay';
    }

    isSelected() {
        return jQuery('#payment_method_buckaroo_googlepay').is(':checked');
    }

    hasContainer() {
        return jQuery('.googlepay-checkout-button-container').length > 0;
    }

    syncTokenField() {
        jQuery('.buckaroo-googlepay-payment-data').val(this.token || '');
    }

    init() {
        const self = this;

        jQuery(document.body).on('updated_checkout', () => {
            self.maybeBuild();
            if (self.token) {
                self.syncTokenField();
            }
        });
        this.maybeBuild();

        jQuery('form.checkout').on('checkout_place_order_buckaroo_googlepay', () => {
            if (self.token) {
                self.syncTokenField();
                return true;
            }
            self.beginPayment();
            return false;
        });
    }

    maybeBuild() {
        if (!this.isSelected() || !this.hasContainer()) {
            return;
        }
        if (!window.BuckarooGooglePay || typeof window.BuckarooGooglePay.create !== 'function') {
            return;
        }

        const self = this;

        try {
            this.instance = window.BuckarooGooglePay.create({
                isOnCheckout: true,
                containerSelector: '.googlepay-checkout-button-container',
                buttonElementId: 'googlepay-checkout-button-element',
                onAuthorized: payment => {
                    self.token = JSON.stringify(payment);
                    self.syncTokenField();
                    jQuery('form.checkout').submit();
                },
            });

            this.instance.rebuild();
            this.instance.init();
        } catch (e) {
            // Google Pay unavailable in this context; method stays inert.
        }
    }

    beginPayment() {
        if (this.instance && this.instance.triggerPayment() === true) {
            return true;
        }

        // The Buckaroo SDK refuses to build a Google Pay session when the gateway
        // is misconfigured (e.g. an empty Google Merchant ID), which would leave
        // "Place order" doing nothing at all. Say so instead of failing silently.
        this.displayError(
            'Google Pay is not available in this browser or context. Please choose another payment method.'
        );

        return false;
    }

    displayError(message) {
        jQuery('.woocommerce-notices-wrapper')
            .first()
            .prepend(`<div class="woocommerce-error" role="alert">${message}</div>`);
        jQuery('html, body').scrollTop(0);
    }
}

export default BuckarooGooglePayCheckout;
