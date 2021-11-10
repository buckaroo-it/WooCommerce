(function($) {
    "use strict";

    $('body').on('change', 'input[name="payment_method"]', function() {
        $('body').trigger('update_checkout');
    });
    $('body').on('updated_checkout', function(e) {
        buckaroo_afterpay();
        buckaroo_afterpaynew();
        buckaroo_bilink();
        buckaroo_klarna();
    });
    /**
     * toggle between bilink payment types on company name change
     */
    $('#billing_company').on('input', function() {
        buckaroo_bilink_toggle($(this).val());
    });
    /**
     * toggle in3 coc & company name if debtor
     */
    $('body').on('change', '#buckaroo-in3-orderas', function() {
        $('#buckaroo-in3-coc-container, #buckaroo-in3-companyname-container')
            .toggle($(this).val().toLowerCase() !== 'debtor');
    });

    /**
     * toggle afterpay b2b fields
     */
    $('body').on('change', '#buckaroo-afterpay-b2b', function() {
        let b2bActive = $(this).is(':checked');
        let birthdate = $('#buckaroo-afterpay-birthdate');
        let birthdateParent = birthdate.parent();

        let gender = $('[name="buckaroo-afterpay-gender"]');
        let genderParent = gender.parent();

        $('#showB2BBuckaroo').toggle(b2bActive);

        if ($('#billing_company').length) {
            $('#buckaroo-afterpay-CompanyName').val($('#billing_company').val());
        }

        birthdate.prop('disabled', !b2bActive);
        birthdateParent.toggle(!b2bActive);
        birthdateParent.toggleClass('validate-required', !b2bActive);

        gender.prop('disabled', !b2bActive);
        genderParent.toggle(!b2bActive);

    });

    /**
     * toggle phone number
     */
    function buckaroo_afterpay() {
        if ($('input[name=billing_phone]').length) {
            $('#buckaroo-afterpay-phone').parent().hide();
        }
    }
    /**
     * toggle phone number
     */
    function buckaroo_afterpaynew() {
        if ($('input[name=billing_phone]').length) {
            $('#buckaroo-afterpaynew-phone').parent().hide();
        }
    }
    /**
     * toggle between bilink payment types
     */
    function buckaroo_bilink() {
        let billinkCompany = $('#billing_company');
        if (billinkCompany.length) {
            buckaroo_bilink_toggle(billinkCompany.val());
        }
    }
    /**
     * toggle between bilink payment types
     * 
     * @param {string} val Company field value
     */
    function buckaroo_bilink_toggle(val) {
        let billinkB2b = $('#buckaroo_billink_b2b');
        let billinkB2c = $('#buckaroo_billink_b2c');
        if (billinkB2b.length && billinkB2c.length) {
            var toggleState = $.trim(val).length > 0;
            billinkB2b.toggle(toggleState);
            billinkB2c.toggle(!toggleState);
        }
    }
    /**
     * hide karna phone if exists
     */
    function buckaroo_klarna() {
        if ($('input[name=billing_phone]').length) {
            $('input[id^="buckaroo-klarna"][type="tel"]').parent().hide();
        }
    }
})(jQuery);