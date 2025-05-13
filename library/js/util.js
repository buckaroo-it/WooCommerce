// This file holds the JS & jQuery behind the uploading
// and management of Buckaroo certificates, for the
// master settings page & all buckaroo payment methods.
jQuery(document).ready(function () {
    if (
        jQuery('.wrap.woocommerce label').first().attr('for') &&
        jQuery('.wrap.woocommerce label').first().attr('for').lastIndexOf('_')
    ) {
        //Get Location
        var label = jQuery('.wrap.woocommerce label').first().attr('for');
        var label = label.substring(0, label.lastIndexOf('_'));
        var ifbuck = label.substring(0, label.lastIndexOf('_'));
        var locationName = label.substring(label.lastIndexOf('_'), label.length);
        if (ifbuck == 'woocommerce_buckaroo') {
            jQuery('select').each(function () {
                this.style.padding = '2px';
            });
        }
    }
    buckarooAdmin.init();
});

buckarooAdmin = {
    testButton: function () {
        let buckarooTestButton = jQuery('[id$="test_credentials"]');
        buckarooTestButton.addClass('button-primary');
        buckarooTestButton.val(buckarooTestButton.attr('title'));

        buckarooTestButton.on('click', function () {
            let website_key = jQuery('[name^="woocommerce_buckaroo_"][name$="_merchantkey"]').val();
            let secret_key = jQuery('[name^="woocommerce_buckaroo_"][name$="_secretkey"]').val();
            jQuery.post(
                ajaxurl,
                {
                    action: 'buckaroo_test_credentials',
                    website_key,
                    secret_key,
                },
                function (response) {
                    alert(response);
                }
            );
        });
    },
    credicardToggleSelect: function () {
        this.setCredicardSeparate(jQuery('#woocommerce_buckaroo_creditcard_creditcardmethod').val());
        var self = this;
        jQuery('#woocommerce_buckaroo_creditcard_creditcardmethod').on('change', function () {
            self.setCredicardSeparate(jQuery(this).val());
        });
    },
    setCredicardSeparate(value) {
        jQuery('#woocommerce_buckaroo_creditcard_show_in_checkout')
            .closest('tr')
            .toggle(value === 'encrypt');

        const hiddenProviders = [
            'cartebancaire',
            'cartebleuevisa',
            'dankort',
            'nexi',
            'postepay',
            'visaelectron',
            'vpay',
        ];

        const selector = hiddenProviders.map(v => `option[value = ${v}]`).join(', ');

        if (value === 'encrypt') {
            jQuery(selector, '#woocommerce_buckaroo_creditcard_AllowedProvider').hide().prop('selected', false);
            jQuery(selector, '#woocommerce_buckaroo_creditcard_show_in_checkout').hide().prop('selected', false);
        } else {
            jQuery(selector, '#woocommerce_buckaroo_creditcard_AllowedProvider').show();
            jQuery(selector, '#woocommerce_buckaroo_creditcard_show_in_checkout').show();
        }
    },

    in3ToggleLogoSelector() {
        const iconSelector = jQuery('.bk-in3-logo-wrap').closest('tr');
        const apiVersionSelector = jQuery('#woocommerce_buckaroo_in3_api_version');
        iconSelector.toggle(apiVersionSelector.val() === 'v3');
        apiVersionSelector.on('change', function () {
            let canShowIconSelector = jQuery(this).val() === 'v3';
            iconSelector.toggle(canShowIconSelector);
        });
    },

    in3FrontEndLabel() {
        jQuery('#woocommerce_buckaroo_in3_api_version').on('change', function () {
            let apiVersion = jQuery(this).val();
            let titleField = jQuery('#woocommerce_buckaroo_in3_title');

            const label =
                apiVersion === buckaroo_php_vars.version2 ? buckaroo_php_vars.in3_v2 : buckaroo_php_vars.in3_v3;
            titleField.val(label);
        });
    },

    init: function () {
        this.testButton();
        this.credicardToggleSelect();
        this.in3ToggleLogoSelector();
        this.in3FrontEndLabel();
    },
};
