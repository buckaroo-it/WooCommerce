import * as async   from './helpers/async.js';
import ApplePay     from './applepay.js';
import Woocommerce  from './woocommerce.js';

"use strict";

const BuckarooListenToApplePayChange = function (applepay)  {
    if (applepay === undefined) {
      return;
    }

    return function() {
      applepay.rebuild();
      applepay.init();
    }
}
window.BuckarooInitApplePay = function () {
  if (jQuery === undefined) {
    console.error("Cannot initialize ApplePay missing jquery");
    return;
  }
  const applepay = new ApplePay;
  applepay.rebuild();
  applepay.init();
  document.removeEventListener("applepayRefresh", BuckarooListenToApplePayChange(applepay))
  document.addEventListener("applepayRefresh", BuckarooListenToApplePayChange(applepay))
}


jQuery(function() {
  document.dispatchEvent(new Event("bk-jquery-loaded"));
  if (jQuery('.applepay-button-container')[0]) {
                
      const applepay = new ApplePay;
      const woocommerce = new Woocommerce;
      const rebuild_and_init = async.debounce(() => {
        applepay.rebuild();
        if (woocommerce.canOrderAmount()) {
          applepay.init();
        }
      });
  
      if (jQuery(".variations_form").length === 0) {
        rebuild_and_init();
        jQuery(".cart .quantity input").change(() => {
          rebuild_and_init();
        });
      }
  
      jQuery(".variations_form").on("show_variation", () => {
        rebuild_and_init();
        jQuery(".cart .quantity input").change(() => {
          rebuild_and_init();
        });
      });
  
      jQuery(".variations_form").on("hide_variation", () => {
        applepay.rebuild();
      });
  
      jQuery(document.body).on('wc_fragments_refreshed', () => {
        rebuild_and_init();
      });
  
      jQuery(document.body).on('updated_shipping_method', () => {
        applepay.rebuild();
        applepay.init();
      });
  }
})