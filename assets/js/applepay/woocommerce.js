import * as convert from './helpers/convert.js';

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
        product_id: current_shown_product.product_id,
        variation_id: current_shown_product.variation_id,
        quantity: jQuery('.cart .quantity input').val() || 1,
        country_code,
      };

      let all_items = [];
      jQuery.ajax({
        url: this.url,
        data: send_data,
        async: false,
        dataType: 'json',
      })
        .done((items) => {
          all_items = items.map((item) => ({
            id: item.id,
            name: item.name,
            price: convert.toDecimal(item.price),
            quantity: item.quantity,
            type: item.type,
            attributes: item.attributes,
          }));
        });
      return all_items;
    }
    let cart_items = [];
    jQuery.ajax({
      url: this.url,
      data: { 'wc-api': `${this.api_namespace}-get-items-from-cart` },
      async: false,
      dataType: 'json',
    })
      .done((items) => {
        cart_items = items.map((item) => ({
          id: item.id,
          name: item.name,
          price: convert.toDecimal(item.price),
          quantity: item.quantity,
          type: item.type,
          attributes: item.attributes,
        }));
      });
    return cart_items;
  }

  getShippingMethods(country_code) {
    const product_params = (() => {
      if (jQuery('.applepay-button-container').hasClass('is-detail-page')) {
        const current_shown_product = this.getCurrentShownProduct();

        return {
          product_id: current_shown_product.product_id,
          variation_id: current_shown_product.variation_id,
          quantity: jQuery('.cart .quantity input').val() || 1,
        };
      }
      return {};
    })();

    const default_params = {
      'wc-api': `${this.api_namespace}-get-shipping-methods`,
      country_code,
    };

    let methods;
    jQuery.ajax({
      url: this.url,
      data: Object.assign(default_params, product_params),
      dataType: 'json',
      async: false,
    })
      .done((response) => { methods = response; });

    return methods;
  }

  getStoreInformation() {
    let information = [];
    jQuery.ajax({
      url: this.url,
      data: { 'wc-api': `${this.api_namespace}-get-shop-information` },
      async: false,
      dataType: 'json',
    })
      .done((response) => { information = response; });

    return information;
  }

  getCurrentShownProduct() {
    const product_id = jQuery('[name="add-to-cart"]').val();

    const variation_id = (() => {
      if (jQuery('[name="variation_id"]')[0]
                && jQuery('[name="variation_id"]').val() != 0
                && jQuery('[name="variation_id"]')[0] != ''
      ) {
        return jQuery('[name="variation_id"]').val();
      }
      return product_id;
    })();

    return {
      product_id,
      variation_id,
    };
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
