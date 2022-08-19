class BuckarooIdin {
  listen() {
    const self = this;
    jQuery("#buckaroo-idin-verify-button").click(() => {
      if (
        jQuery("#buckaroo-idin-issuer") &&
        jQuery("#buckaroo-idin-issuer").val().length > 1
      ) {
        self.identify(jQuery("#buckaroo-idin-issuer").val());
      } else {
        self.displayErrorMessage(buckaroo_global.idin_i18n.bank_required);
      }
    });
  }
  disableBlock(blockSelector) {
    jQuery(blockSelector).block({
      message: null,
      overlayCSS: {
        background: "#fff",
        opacity: 0.6,
      },
    });
  }

  identify(issuer) {
    const self = this;
    self.disableBlock(".checkout.woocommerce-checkout");

    jQuery
      .ajax({
        url: buckaroo_global.ajax_url,
        data: {
          "wc-api": "WC_Gateway_Buckaroo_idin-identify",
          issuer: issuer,
        },
        dataType: "json",
      })
      .done((response) => {
        jQuery(".woocommerce-checkout").unblock();
        if (response && response.message) {
          self.displayErrorMessage(response.message);
        } else if (response && response.result == "success") {
          window.location.replace(response.redirect);
        } else {
          self.displayErrorMessage(buckaroo_global.idin_i18n.general_error);
        }
      })
      .fail(() => {
        self.displayErrorMessage(buckaroo_global.idin_i18n.general_error);
        jQuery(".woocommerce-checkout").unblock();
      });
  }
  displayErrorMessage(message) {
    const content = `      
        <div class="woocommerce-error" role="alert">
          ${message}
        </div>
      `;
    jQuery(".woocommerce-notices-wrapper").first().prepend(content);
    var wooError = jQuery(
      ".woocommerce-notices-wrapper .woocommerce-error"
    ).first();
    setTimeout(function () {
      wooError.fadeOut(1000);
    }, 10000);
    jQuery("html, body").scrollTop(0);
  }
}

export default BuckarooIdin;