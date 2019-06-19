import * as async   from './helpers/async.js';
import ApplePay     from './applepay.js';
import Woocommerce  from './woocommerce.js';

"use strict";

if ($('.applepay-button-container')[0]) {            
  const load_buckaroo_sdk = new Promise((resolve) => {
    var buckaroo_sdk = document.createElement("script");
    buckaroo_sdk.src = "https://checkout.buckaroo.nl/api/buckaroosdk/script/en-US";
    buckaroo_sdk.async = true;
    document.head.appendChild(buckaroo_sdk);
    buckaroo_sdk.onload = () => {
      resolve();  
    };
  });

  load_buckaroo_sdk.then(() => {
    const applepay = new ApplePay;
    const woocommerce = new Woocommerce;
    const rebuild_and_init = async.debounce(() => {
      applepay.rebuild();
      if (woocommerce.canOrderAmount()) {
        applepay.init();
      }
    });

    if ($(".variations_form").length === 0) {
      rebuild_and_init();
      $(".cart .quantity input").change(() => {
        rebuild_and_init();
      });
    }

    $(".variations_form").on("show_variation", () => {
      rebuild_and_init();
      $(".cart .quantity input").change(() => {
        rebuild_and_init();
      });
    });

    $(".variations_form").on("hide_variation", () => {
      applepay.rebuild();
    });

    $(document.body).on('wc_fragments_refreshed', () => {
      rebuild_and_init();
    });

    $(document.body).on('updated_shipping_method', () => {
      applepay.rebuild();
      applepay.init();
    });
  });
}  
