/**
 * Apple Pay as a standard payment method on the classic (shortcode) checkout.
 *
 * The Apple Pay sheet only authorises payment; billing and shipping are taken
 * from the WooCommerce checkout form. On authorisation the encrypted token is
 * written to the hidden `paymentData` field and the checkout form is submitted
 * normally, so WooCommerce processes the order with the entered addresses.
 */
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

    init() {
        const self = this;

        // (Re)build the Apple Pay button whenever the checkout fragment refreshes
        // (WooCommerce re-renders the payment box on every `updated_checkout`).
        jQuery(document.body).on('updated_checkout', () => self.maybeBuild());
        this.maybeBuild();

        // Require an authorised token before the order is placed. The place-order
        // click is a user gesture, so opening the Apple Pay sheet from here is allowed.
        jQuery('form.checkout').on('checkout_place_order_buckaroo_applepay', event => {
            if (self.token) {
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
                // No button: the standard method is triggered from "Place Order".
                renderButton: false,
                containerSelector: '.applepay-checkout-button-container',
                onAuthorized: payment => {
                    self.token = JSON.stringify(payment);
                    jQuery('.buckaroo-applepay-payment-data').val(self.token);
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
