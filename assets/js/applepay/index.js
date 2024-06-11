import debounce from './helpers/async';
import ApplePay from './applepay';
import Woocommerce from './woocommerce';

'use strict';

const BuckarooListenToApplePayChange = function (applepay) {
  if (applepay === undefined) {
    return;
  }

  return function () {
    applepay.rebuild();
    applepay.init();
  };
};

window.BuckarooInitApplePay = function () {
  if (typeof jQuery === 'undefined') {
    console.error('Cannot initialize ApplePay missing jquery');
    return;
  }
  const applepay = new ApplePay();
  const handleApplePayChange = BuckarooListenToApplePayChange(applepay);

  if (handleApplePayChange) {
    applepay.rebuild();
    applepay.init();
    document.removeEventListener('applepayRefresh', handleApplePayChange);
    document.addEventListener('applepayRefresh', handleApplePayChange);
  }
};

jQuery(() => {
  document.dispatchEvent(new Event('bk-jquery-loaded'));
  if (jQuery('.applepay-button-container')[0]) {
    const applepay = new ApplePay();
    const woocommerce = new Woocommerce();
    const rebuildAndInit = debounce(() => {
      applepay.rebuild();
      if (woocommerce.canOrderAmount()) {
        applepay.init();
      }
    });

    if (jQuery('.variations_form').length === 0) {
      rebuildAndInit();
      jQuery('.cart .quantity input').change(() => {
        rebuildAndInit();
      });
    }

    jQuery('.variations_form').on('show_variation', () => {
      rebuildAndInit();
      jQuery('.cart .quantity input').change(() => {
        rebuildAndInit();
      });
    });

    jQuery('.variations_form').on('hide_variation', () => {
      applepay.rebuild();
    });

    jQuery(document.body).on('wc_fragments_refreshed', () => {
      rebuildAndInit();
    });

    jQuery(document.body).on('updated_shipping_method', () => {
      applepay.rebuild();
      applepay.init();
    });
  }
});
