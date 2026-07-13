class BuckarooApplePayCheckout {
    constructor() {
        this.instance = null;
        this.token = null;
        this.methodId = 'buckaroo_applepay';
    }

    isSelected() {
        return jQuery('#payment_method_buckaroo_applepay').is(':checked');
    }

    hasContainer() {
        return jQuery('.applepay-checkout-button-container').length > 0;
    }

    syncTokenField() {
        jQuery('.buckaroo-applepay-payment-data').val(this.token || '');
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

        jQuery('form.checkout').on('checkout_place_order_buckaroo_applepay', event => {
            if (self.token) {
                self.syncTokenField();
                return true;
            }
            self.beginPayment(event);
            return false;
        });
    }

    maybeBuild() {
        if (!this.isSelected() || !this.hasContainer()) {
            return;
        }
        if (!window.BuckarooApplePay || typeof window.BuckarooApplePay.create !== 'function') {
            return;
        }

        const self = this;

        try {
            this.instance = window.BuckarooApplePay.create({
                isOnCheckout: true,
                renderButton: false,
                containerSelector: '.applepay-checkout-button-container',
                onAuthorized: payment => {
                    self.token = JSON.stringify(payment);
                    self.syncTokenField();
                    jQuery('form.checkout').submit();
                },
            });

            this.instance.rebuild();
            this.instance.init();
        } catch (e) {
            // Apple Pay unavailable in this context; method stays inert.
        }
    }

    beginPayment(event) {
        if (this.instance && typeof this.instance.triggerPayment === 'function') {
            return this.instance.triggerPayment(event);
        }
        return false;
    }
}

export default BuckarooApplePayCheckout;
