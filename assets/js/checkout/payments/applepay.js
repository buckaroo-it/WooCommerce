/**
 * Apple Pay as a standard payment method on the classic (shortcode) checkout.
 *
 * The Apple Pay sheet only authorises payment; billing and shipping are taken
 * from the WooCommerce checkout form. On authorisation the encrypted token is
 * written to the hidden `paymentData` field and the checkout form is submitted
 * normally, so WooCommerce processes the order with the entered addresses.
 */

// Temporary diagnostic logging (see applepay bundle). Keeps the classic
// checkout stages visible in the console while the token issue is verified.
function bkLog(stage, details) {
    try {
        // eslint-disable-next-line no-console
        console.log(`[Buckaroo ApplePay][classic:${stage}]`, details);
    } catch (e) {
        // never break checkout because of logging
    }
}

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

    /**
     * (Re)write the authorised token into the hidden field.
     *
     * The payment box is re-rendered on every `updated_checkout` fragment
     * refresh (e.g. after a failed submission or address change), which
     * replaces the hidden input with an EMPTY one while `this.token` is still
     * set. Without re-writing it right before submission, WooCommerce would
     * POST an empty `paymentData` and the Buckaroo API would receive an empty
     * token. Always sync the field from the stored token.
     */
    syncTokenField() {
        const field = jQuery('.buckaroo-applepay-payment-data');
        field.val(this.token || '');
        bkLog('syncTokenField', {
            fieldsFound: field.length,
            tokenLength: this.token ? this.token.length : 0,
        });
    }

    init() {
        const self = this;

        // (Re)build the Apple Pay button whenever the checkout fragment refreshes
        // (WooCommerce re-renders the payment box on every `updated_checkout`).
        jQuery(document.body).on('updated_checkout', () => {
            bkLog('updated_checkout', 'payment box re-rendered; rebuilding and re-syncing token field');
            self.maybeBuild();
            // Fragment refresh replaced the hidden input: restore the token.
            if (self.token) {
                self.syncTokenField();
            }
        });
        this.maybeBuild();

        // Require an authorised token before the order is placed. The place-order
        // click is a user gesture, so opening the Apple Pay sheet from here is allowed.
        jQuery('form.checkout').on('checkout_place_order_buckaroo_applepay', event => {
            if (self.token) {
                // Re-write the hidden field at submit time: the payment box may
                // have been re-rendered (emptying the field) since authorisation.
                self.syncTokenField();
                bkLog('place_order', 'Token present; letting WooCommerce submit the form');
                return true;
            }
            bkLog('place_order', 'No token yet; opening the Apple Pay sheet');
            self.beginPayment(event);
            return false;
        });
    }

    maybeBuild() {
        if (!this.isSelected() || !this.hasContainer()) {
            return;
        }
        if (!window.BuckarooApplePay || typeof window.BuckarooApplePay.create !== 'function') {
            bkLog('maybeBuild', 'window.BuckarooApplePay is not available');
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
                    // `payment` is already normalised to a plain object by the
                    // applepay bundle (normalizeApplePayPayment), so stringify
                    // is safe here on every device.
                    self.token = JSON.stringify(payment);
                    bkLog('onAuthorized', {
                        tokenLength: self.token.length,
                        hasToken: !!(payment && payment.token),
                    });
                    self.syncTokenField();
                    jQuery('form.checkout').submit();
                },
            });

            this.instance.rebuild();
            this.instance.init();
        } catch (e) {
            bkLog('maybeBuild:error', e && e.message);
            // Apple Pay unavailable in this context; method stays inert.
        }
    }

    beginPayment(event) {
        if (this.instance && typeof this.instance.triggerPayment === 'function') {
            return this.instance.triggerPayment(event);
        }
        bkLog('beginPayment', 'No Apple Pay instance available');
        return false;
    }
}

export default BuckarooApplePayCheckout;
