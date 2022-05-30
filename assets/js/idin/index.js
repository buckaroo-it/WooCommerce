(function($) {

    var url = buckaroo_global.ajax_url;
    if (url === undefined) {
        url = '/';
    }

    function buckarooDisableBlock(blockSelector) {
        $(blockSelector).block({
            message: null,
            overlayCSS: {
                background: '#fff',
                opacity: 0.6
            }
        });
    }

    function buckarooIdentify(issuer) {
        buckarooDisableBlock('.checkout.woocommerce-checkout');

        setTimeout(() => {
            $.ajax({
                    url,
                    data: {
                        "wc-api": "WC_Gateway_Buckaroo_idin-identify",
                        "issuer": issuer
                    },
                    dataType: "json"
                })
                .done((response) => {
                    if (response) {
                        if (response.result == "success") {
                            window.location.replace(response.redirect);
                            $('.woocommerce-checkout').unblock();
                            return true;
                        } else {
                            if (response.message) {
                                buckarooDisplayErrorMessage(response.message);
                                $('.woocommerce-checkout').unblock();
                                return false;
                            }
                        }
                    }
                    buckarooDisplayErrorMessage(buckaroo_global.idin_i18n.general_error);
                    $('.woocommerce-checkout').unblock();
                })
                .fail(() => {
                    buckarooDisplayErrorMessage(buckaroo_global.idin_i18n.general_error);
                    $('.woocommerce-checkout').unblock();
                });
        }, 1000);
    }

    function buckarooDisplayErrorMessage(message) {
        const content = `      
        <div class="woocommerce-error" role="alert">
          ${message}
        </div>
      `;
        $('.woocommerce-notices-wrapper').first().prepend(content);
        var wooError = $('.woocommerce-notices-wrapper .woocommerce-error').first();
        setTimeout(function() {
            wooError.fadeOut(1000);
        }, 10000);
        $('html, body').scrollTop(0);
    }

    $("#buckaroo-idin-verify-button").click(() => {
        if ($("#buckaroo-idin-issuer") && ($("#buckaroo-idin-issuer").val().length > 1)) {
            buckarooIdentify($("#buckaroo-idin-issuer").val());
        } else {
            buckarooDisplayErrorMessage(buckaroo_global.idin_i18n.bank_required);

        }
    });

})(jQuery);