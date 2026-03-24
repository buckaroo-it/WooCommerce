import debounce from './helpers/async';
import GooglePay from './googlepay';
import Woocommerce from './woocommerce';

('use strict');

const BuckarooListenToGooglePayChange = function (googlepay) {
    if (googlepay === undefined) {
        return;
    }

    return function () {
        googlepay.rebuild();
        googlepay.init();
    };
};

window.BuckarooInitGooglePay = function () {
    if (typeof jQuery === 'undefined') {
        console.error('Cannot initialize GooglePay missing jquery');
        return;
    }
    const googlepay = new GooglePay();
    const handleGooglePayChange = BuckarooListenToGooglePayChange(googlepay);

    if (handleGooglePayChange) {
        googlepay.rebuild();
        googlepay.init();
        document.removeEventListener('googlepayRefresh', handleGooglePayChange);
        document.addEventListener('googlepayRefresh', handleGooglePayChange);
    }
};

jQuery(() => {
    // Skip on blocks checkout — the React component (BuckarooGooglepay) manages the lifecycle there
    if (document.querySelector('.wc-block-checkout') || document.querySelector('.wp-block-woocommerce-checkout')) {
        return;
    }

    if (jQuery('.googlepay-button-container')[0]) {
        const googlepay = new GooglePay();
        const woocommerce = new Woocommerce();
        const rebuildAndInit = debounce(() => {
            googlepay.rebuild();
            if (woocommerce.canOrderAmount()) {
                googlepay.init();
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
            googlepay.rebuild();
        });

        jQuery(document.body).on('wc_fragments_refreshed', () => {
            rebuildAndInit();
        });

        jQuery(document.body).on('updated_shipping_method', () => {
            googlepay.rebuild();
            googlepay.init();
        });
    }
});
