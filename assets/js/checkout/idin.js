/* global buckaroo_global */
/* eslint-disable camelcase */

class BuckarooIdin {
  listen() {
    const self = this;
    jQuery('#buckaroo-idin-verify-button').click(() => {
      const issuer = jQuery('#buckaroo-idin-issuer').val();
      if (issuer && issuer.length > 1) {
        self.identify(issuer);
      } else {
        self.displayErrorMessage(buckaroo_global.idin_i18n.bank_required);
      }
    });
  }

  static disableBlock(blockSelector) {
    jQuery(blockSelector).block({
      message: null,
      overlayCSS: {
        background: '#fff',
        opacity: 0.6,
      },
    });
  }

  identify(issuer) {
    const self = this;
    BuckarooIdin.disableBlock('.checkout.woocommerce-checkout');

    jQuery
      .ajax({
        url: buckaroo_global.ajax_url,
        data: {
          'wc-api': 'WC_Gateway_Buckaroo_idin-identify',
          issuer,
        },
        dataType: 'json',
      })
      .done((response) => {
        jQuery('.woocommerce-checkout').unblock();
        if (response && response.message) {
          self.displayErrorMessage(response.message);
        } else if (response && response.result === 'success') {
          window.location.replace(response.redirect);
        } else {
          self.displayErrorMessage(buckaroo_global.idin_i18n.general_error);
        }
      })
      .fail(() => {
        self.displayErrorMessage(buckaroo_global.idin_i18n.general_error);
        jQuery('.woocommerce-checkout').unblock();
      });
  }

  static displayErrorMessage(message) {
    const content = `      
        <div class="woocommerce-error" role="alert">
          ${message}
        </div>
      `;
    jQuery('.woocommerce-notices-wrapper').first().prepend(content);
    const wooError = jQuery('.woocommerce-notices-wrapper .woocommerce-error').first();
    setTimeout(() => {
      wooError.fadeOut(1000);
    }, 10000);
    jQuery('html, body').scrollTop(0);
  }
}

export default BuckarooIdin;
