import BuckarooPayByBank from './payments/paybybank';
import BuckarooApplePayCheckout from './payments/applepay';
import BuckarooGooglePayCheckout from './payments/googlepay';

class BuckarooCheckout {
    listen() {
        const paybyBank = new BuckarooPayByBank();
        const applePay = new BuckarooApplePayCheckout();
        const googlePay = new BuckarooGooglePayCheckout();
        const self = this;
        paybyBank.init();
        applePay.init();
        googlePay.init();
        jQuery('body').on('change', 'input[name="payment_method"]', () => {
            jQuery('body').trigger('update_checkout');
        });
        // WooCommerce restores payment field values after every checkout refresh
        // and fires `change` on them, which runs its inline validation and marks
        // every empty required Buckaroo field invalid before the shopper has
        // touched it. Remember what the shopper did touch so the rest can be reset.
        const touchedFields = new Set();
        jQuery('body').on('input change focusout', '.buckaroo-checkout-fields :input', function (e) {
            if (!e.isTrigger && this.name) {
                touchedFields.add(this.name);
            }
        });
        // A failed submit validated the selected method's fields on purpose.
        jQuery('body').on('checkout_error', () => {
            jQuery('input[name="payment_method"]:checked')
                .closest('li')
                .find('.buckaroo-checkout-fields :input[name]')
                .each(function () {
                    touchedFields.add(this.name);
                });
        });
        jQuery('body').on('updated_checkout', e => {
            self.resetUntouchedValidation(touchedFields);
            self.afterpay();
            self.afterpaynew();
            self.bilink();
            self.klarna();
            self.initBirthdatePickers();
            paybyBank.onLoad();
        });
        // Initialise on first load as well (payment box may already be present
        // before the first `updated_checkout` fires).
        self.initBirthdatePickers();
        /**
         * toggle between bilink payment types on company name change
         */
        jQuery('#billing_company').on('input', function () {
            self.bilink_toggle(jQuery(this).val());

            jQuery('#buckaroo-afterpaynew-company-coc-registration')
                .closest('.form-row')
                .toggle(jQuery.trim(jQuery(this).val()).length !== 0);
        });

        /**
         * toggle afterpay b2b fields
         */
        jQuery('body').on('change', '#buckaroo-afterpay-b2b', function () {
            const b2bActive = jQuery(this).is(':checked');
            const birthdate = jQuery('#buckaroo-afterpay-birthdate');
            const birthdateParent = birthdate.closest('.form-row');

            const gender = jQuery('[name="buckaroo-afterpay-gender"]');
            const genderParent = gender.parent();

            jQuery('#showB2BBuckaroo').toggle(b2bActive);

            if (jQuery('#billing_company').length) {
                jQuery('#buckaroo-afterpay-company-name').val(jQuery('#billing_company').val());
            }

            birthdate.prop('disabled', b2bActive);
            birthdateParent.toggle(!b2bActive);
            birthdateParent.toggleClass('validate-required', !b2bActive);

            gender.prop('disabled', !b2bActive);
            genderParent.toggle(!b2bActive);
        });
    }

    /**
     * Clear WooCommerce's inline validation state from Buckaroo fields the
     * shopper has not interacted with yet.
     *
     * @param {Set<string>} touchedFields Names of fields the shopper edited
     */
    resetUntouchedValidation(touchedFields) {
        jQuery('.buckaroo-checkout-fields .form-row.woocommerce-invalid').each(function () {
            const $row = jQuery(this);
            const name = $row.find(':input').attr('name');
            if (name && !touchedFields.has(name)) {
                $row.removeClass('woocommerce-invalid woocommerce-invalid-required-field');
                $row.find(':input').removeAttr('aria-invalid');
            }
        });
    }

    /**
     * toggle phone number
     */
    afterpay() {
        if (jQuery('input[name=billing_phone]').length) {
            jQuery('#buckaroo-afterpay-phone').closest('.form-row').hide();
        }
    }

    /**
     * toggle phone number
     */
    afterpaynew() {
        if (jQuery('input[name=billing_phone]').length) {
            jQuery('#buckaroo-afterpaynew-phone').closest('.form-row').hide();
        }
        jQuery('#buckaroo-afterpaynew-company-coc-registration')
            .closest('.form-row')
            .toggle(jQuery.trim(jQuery('input[name=billing_company]').val()).length !== 0);
    }

    /**
     * toggle between bilink payment types
     */
    bilink() {
        const billinkCompany = jQuery('#billing_company');
        if (billinkCompany.length) {
            this.bilink_toggle(billinkCompany.val());
        }
    }

    /**
     * Show the billink company fields only when a company is filled in. B2C
     * needs no fields of its own, so there is nothing to show in that case.
     *
     * @param {string} val Company field value
     */
    bilink_toggle(val) {
        const billinkB2b = jQuery('#buckaroo_billink_b2b');
        if (billinkB2b.length) {
            billinkB2b.toggle(jQuery.trim(val).length > 0);
        }
    }

    /**
     * Shared birthdate datepicker initialiser for every BNPL method in the
     * classic (legacy) checkout.
     *
     */
    initBirthdatePickers() {
        // jquery-ui-datepicker is a WooCommerce/WordPress core dependency, but
        // guard anyway so a missing dependency degrades to a plain text field
        // (still typeable as DD-MM-YYYY) instead of throwing.
        if (typeof jQuery.fn.datepicker !== 'function') {
            return;
        }

        jQuery('input[id^="buckaroo-"][id$="-birthdate"]').each(function () {
            const $field = jQuery(this);

            // Re-initialise cleanly if a previous instance is still attached.
            if ($field.hasClass('hasDatepicker')) {
                try {
                    $field.datepicker('destroy');
                } catch (e) {
                    // ignore – field will be re-initialised below
                }
            }

            $field.datepicker({
                dateFormat: 'dd-mm-yy', // jQuery UI 'yy' = 4-digit year => DD-MM-YYYY
                changeMonth: true,
                changeYear: true,
                yearRange: '1900:+0',
                minDate: new Date(1900, 0, 1),
                maxDate: 0, // no future dates – birthdate must be in the past
                defaultDate: '-18y', // open near a plausible adult birth year
                onClose(selectedDate) {
                    // Mirror the value back so WooCommerce validation / fragment
                    // serialisation always sees the latest selection.
                    jQuery(this).val(selectedDate).trigger('change');
                },
            });
        });
    }

    /**
     * hide karna phone if exists
     */
    klarna() {
        if (jQuery('input[name=billing_phone]').length) {
            jQuery('input[id^="buckaroo-klarna"][type="tel"]').closest('.form-row').hide();
        }
    }
}

export default BuckarooCheckout;
