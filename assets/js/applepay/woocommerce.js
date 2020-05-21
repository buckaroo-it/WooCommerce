import * as convert from './helpers/convert.js';

export default class Woocommerce {
  constructor() {
    this.api_namespace = 'WC_Gateway_Buckaroo_applepay';
  }

  getItems(country_code) {
    if ($('.applepay-button-container').hasClass('is-detail-page')) {
      const current_shown_product = this.getCurrentShownProduct();

      const send_data = {
        'wc-api': `${this.api_namespace}-get-items-from-detail-page`,
        product_id: current_shown_product.product_id,
        variation_id: current_shown_product.variation_id,
        quantity: $(".cart .quantity input").val() || 1,
        country_code: country_code,
      }
      
      var all_items = [];
      $.ajax({
        url: "/",
        data: send_data,
        async: false,
        dataType: "json"
      })
      .done((items) => { 
        all_items = items.map((item) => {
          return {
            id: item.id,
            name: item.name,
            price: convert.toDecimal(item.price),
            quantity: item.quantity,
            type: item.type,
            attributes: item.attributes
          }
        }); 
      });                
      return all_items;
    }

    else {
      var cart_items = [];
      $.ajax({
        url: "/",
        data: { 'wc-api': `${this.api_namespace}-get-items-from-cart` },
        async: false,
        dataType: "json"
      })
      .done((items) => { 
        cart_items = items.map((item) => {
          return {
            id: item.id,
            name: item.name,
            price: convert.toDecimal(item.price),
            quantity: item.quantity,
            type: item.type,
            attributes: item.attributes
          }
        }); 
      });                
      return cart_items;
    }
  }

  getShippingMethods(country_code) {
    const product_params = (() => {
      if ($('.applepay-button-container').hasClass('is-detail-page')) {
        const current_shown_product = this.getCurrentShownProduct();

        return {
          product_id: current_shown_product.product_id,
          variation_id: current_shown_product.variation_id,
          quantity: $(".cart .quantity input").val() || 1,
        }
      }
      return {};
    })();

    const default_params = {
      'wc-api': `${this.api_namespace}-get-shipping-methods`,
      country_code: country_code
    }

    var methods;
    $.ajax({
      url: '/',
      data: Object.assign(default_params, product_params),
      dataType: "json",
      async: false
    })
    .done((response) => { methods = response; });
    
    return methods;
  }

  getStoreInformation() {
    var information = [];
    $.ajax({
      url: "/",
      data: { 'wc-api': `${this.api_namespace}-get-shop-information` },
      async: false,
      dataType: "json"
    })
    .done((response) => { information = response; });

    return information;
  }

  getCurrentShownProduct() {
    const product_id = $('[name="add-to-cart"]').val();

    const variation_id = (() => {
      if ($('[name="variation_id"]')[0] && 
          $('[name="variation_id"]').val() != 0 && 
          $('[name="variation_id"]')[0] != ''
      ) {
        return $('[name="variation_id"]').val();
      }      
      return product_id;
    })();

    return {
      product_id: product_id,
      variation_id: variation_id
    }
  }

  displayErrorMessage(message) {
    const content = `      
      <div class="woocommerce-message" role="alert">
        ${message}
      </div>
    `;

    $('.woocommerce-notices-wrapper').first().prepend(content);
    $('html, body').scrollTop(0);
  }

  canOrderAmount() {
    const current_amount = parseInt($(".cart .quantity input.qty").val());
    const max_amount = parseInt($(".cart .quantity input.qty").attr('max'));
    if (isNaN(max_amount)) {
      return current_amount > 0;
    }
    else {
      return current_amount > 0 && current_amount <= max_amount;
    }
  }
}