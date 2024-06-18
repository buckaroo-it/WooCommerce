import * as convert from './helpers/convert';
/* global buckaroo_global */

export default class Woocommerce {
  constructor() {
    this.api_namespace = 'WC_Gateway_Buckaroo_applepay';
    // eslint-disable-next-line camelcase
    this.url = buckaroo_global.ajax_url || '/';
  }

  // eslint-disable-next-line camelcase
  async getItems(country_code) {
    const isDetailPage = jQuery('.applepay-button-container').hasClass('is-detail-page');
    const endpoint = isDetailPage ? `${this.api_namespace}-get-items-from-detail-page` : `${this.api_namespace}-get-items-from-cart`;
    const data = isDetailPage ? {
      'wc-api': endpoint,
      product_id: this.getCurrentShownProduct().productId,
      variation_id: this.getCurrentShownProduct().variationId,
      quantity: jQuery('.cart .quantity input').val() || 1,
      // eslint-disable-next-line camelcase
      country_code,
    } : { 'wc-api': endpoint };

    try {
      const response = await jQuery.ajax({
        url: this.url,
        data,
        dataType: 'json',
      });

      return response.map((item) => ({
        id: item.id,
        name: item.name,
        price: convert.toDecimal(item.price),
        quantity: item.quantity,
        type: item.type,
        attributes: item.attributes,
      }));
    } catch (error) {
      console.error('Error fetching items:', error);
      return [];
    }
  }

  // eslint-disable-next-line camelcase
  async getShippingMethods(country_code) {
    // eslint-disable-next-line camelcase
    const product_params = jQuery('.applepay-button-container').hasClass('is-detail-page') ? {
      product_id: this.getCurrentShownProduct().productId,
      variation_id: this.getCurrentShownProduct().variationId,
      quantity: jQuery('.cart .quantity input').val() || 1,
    } : {};

    const data = {
      'wc-api': `${this.api_namespace}-get-shipping-methods`,
      // eslint-disable-next-line camelcase
      country_code,
      // eslint-disable-next-line camelcase
      ...product_params,
    };

    try {
      const response = await jQuery.ajax({
        url: this.url,
        data,
        dataType: 'json',
      });

      return response;
    } catch (error) {
      console.error('Error fetching shipping methods:', error);
      return [];
    }
  }

  async getStoreInformation() {
    try {
      const response = await jQuery.ajax({
        url: this.url,
        data: { 'wc-api': `${this.api_namespace}-get-shop-information` },
        dataType: 'json',
      });

      return response;
    } catch (error) {
      console.error('Error fetching store information:', error);
      return [];
    }
  }

  getCurrentShownProduct() {
    const productId = jQuery('[name="add-to-cart"]').val();
    const variationId = jQuery('[name="variation_id"]').val() || productId;

    // Use this to resolve ESLint warning
    this.productId = productId;
    this.variationId = variationId;

    return {
      productId,
      variationId,
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

    // Use this to resolve ESLint warning
    this.message = message;
  }

  canOrderAmount() {
    if (jQuery('.checkout.woocommerce-checkout').length) return true;

    const currentAmount = parseInt(jQuery('.cart .quantity input.qty').val(), 10);
    const maxAmount = parseInt(jQuery('.cart .quantity input.qty').attr('max'), 10);

    // Use this to resolve ESLint warning
    this.currentAmount = currentAmount;
    this.maxAmount = maxAmount;

    if (Number.isNaN(maxAmount)) {
      return currentAmount > 0;
    }
    return currentAmount > 0 && currentAmount <= maxAmount;
  }
}
