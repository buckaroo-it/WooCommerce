import * as convert from './helpers/convert.js';
import { getExpressProductParams, getExpressRequestError } from '../express/product.js';

export default class Woocommerce {
    constructor() {
        this.api_namespace = 'WC_Gateway_Buckaroo_applepay';
        this.url = buckaroo_global.ajax_url;
        if (this.url === undefined) {
            this.url = '/';
        }
    }

    getItems(country_code) {
        if (jQuery('.applepay-button-container').hasClass('is-detail-page')) {
            const current_shown_product = this.getCurrentShownProduct();

            const send_data = {
                'wc-api': `${this.api_namespace}-get-items-from-detail-page`,
                ...current_shown_product,
                country_code,
            };

            let all_items = [];
            let request_error = null;
            jQuery
                .ajax({
                    url: this.url,
                    data: send_data,
                    async: false,
                    dataType: 'json',
                })
                .done(items => {
                    all_items = items.map(item => ({
                        id: item.id,
                        name: item.name,
                        price: convert.toDecimal(item.price),
                        quantity: item.quantity,
                        type: item.type,
                        attributes: item.attributes,
                    }));
                })
                .fail(response => {
                    request_error = getExpressRequestError(response, 'Unable to calculate Apple Pay cart.');
                });
            if (request_error) throw new Error(request_error);
            return all_items;
        }
        let cart_items = [];
        let request_error = null;
        jQuery
            .ajax({
                url: this.url,
                data: {
                    'wc-api': `${this.api_namespace}-get-items-from-cart`,
                },
                async: false,
                dataType: 'json',
            })
            .done(items => {
                cart_items = items.map(item => ({
                    id: item.id,
                    name: item.name,
                    price: convert.toDecimal(item.price),
                    quantity: item.quantity,
                    type: item.type,
                    attributes: item.attributes,
                }));
            })
            .fail(response => {
                request_error = getExpressRequestError(response, 'Unable to load the WooCommerce cart.');
            });
        if (request_error) throw new Error(request_error);
        return cart_items;
    }

    getShippingMethods(country_code) {
        const product_params = (() => {
            if (jQuery('.applepay-button-container').hasClass('is-detail-page')) {
                const current_shown_product = this.getCurrentShownProduct();

                return current_shown_product;
            }
            return {};
        })();

        const default_params = {
            'wc-api': `${this.api_namespace}-get-shipping-methods`,
            country_code,
        };

        let methods;
        let request_error = null;
        jQuery
            .ajax({
                url: this.url,
                data: Object.assign(default_params, product_params),
                dataType: 'json',
                async: false,
            })
            .done(response => {
                methods = response;
            })
            .fail(response => {
                request_error = getExpressRequestError(response, 'Unable to calculate Apple Pay shipping.');
            });

        if (request_error) throw new Error(request_error);

        return methods;
    }

    /**
     * Grand total of the current cart (incl. chosen shipping, payment fee,
     * coupons and taxes). Used by the standard checkout method so the amount
     * authorised in the Apple Pay sheet always equals the amountDebit that is
     * sent to Buckaroo (Buckaroo rejects the transaction on a mismatch).
     *
     * @returns {{total: number, shipping: number, shipping_label: string}|null}
     */
    getCartTotal() {
        let totals = null;
        let request_error = null;
        jQuery
            .ajax({
                url: this.url,
                data: {
                    'wc-api': `${this.api_namespace}-get-cart-total`,
                },
                async: false,
                dataType: 'json',
            })
            .done(response => {
                totals = response;
            })
            .fail(response => {
                request_error = getExpressRequestError(response, 'Unable to calculate Apple Pay cart.');
            });

        if (request_error) throw new Error(request_error);

        return totals;
    }

    getStoreInformation() {
        let information = [];
        jQuery
            .ajax({
                url: this.url,
                data: {
                    'wc-api': `${this.api_namespace}-get-shop-information`,
                },
                async: false,
                dataType: 'json',
            })
            .done(response => {
                information = response;
            });

        return information;
    }

    getCurrentShownProduct() {
        return getExpressProductParams(jQuery);
    }

    displayErrorMessage(message) {
        const content = `      
      <div class="woocommerce-message" role="alert">
        ${message}
      </div>
    `;

        jQuery('.woocommerce-notices-wrapper').first().prepend(content);
        jQuery('html, body').scrollTop(0);
    }

    canOrderAmount() {
        if (jQuery('.checkout.woocommerce-checkout').length) return true;

        const current_amount = parseInt(jQuery('.cart .quantity input.qty').val());
        const max_amount = parseInt(jQuery('.cart .quantity input.qty').attr('max'));
        if (isNaN(max_amount)) {
            return current_amount > 0;
        }
        return current_amount > 0 && current_amount <= max_amount;
    }
}
