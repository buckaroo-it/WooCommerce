import Woocommerce from './woocommerce.js';

export default class Buckaroo {
  constructor() {
    this.woocommerce = new Woocommerce;
  }

  createTransaction(payment_data, total_price, selected_shipping_method, items) {
    $.ajax({
      url: "/?wc-api=WC_Gateway_Buckaroo_applepay-create-transaction",
      method: "post",
      data: {
        selected_shipping_method: selected_shipping_method,
        paymentData: payment_data,
        amount: total_price,
        items: items
      },
      dataType: 'json',
      async: false
    })
    .done((buckaroo_response) => {
      if (buckaroo_response.result == "success") {
        window.location.replace(buckaroo_response.redirect);
      }
      else {
        this.woocommerce.displayErrorMessage(buckaroo_response.message);
      }
    })
    .fail(() => {
      this.woocommerce.displayErrorMessage(
        "Something went wrong while processing your payment."
      );
    });
  }
}