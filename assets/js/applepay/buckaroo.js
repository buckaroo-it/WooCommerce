import Woocommerce from './woocommerce.js';

export default class Buckaroo {
  constructor() {
    this.woocommerce = new Woocommerce();
  }

  createTransaction(payment_data, total_price, selected_shipping_method, items) {
    jQuery.ajax({
      url: '/?wc-api=WC_Gateway_Buckaroo_applepay-create-transaction',
      method: 'post',
      data: {
        selected_shipping_method,
        paymentData: payment_data,
        amount: total_price,
        items,
      },
      dataType: 'json',
      async: false,
    })
      .done((buckaroo_response) => {
        if (buckaroo_response.result == 'success') {
          window.location.replace(buckaroo_response.redirect);
        } else {
          let errorMessage = 'Something went wrong while processing your payment.';
          if (buckaroo_response.message) {
            errorMessage = buckaroo_response.message;
          }

          this.woocommerce.displayErrorMessage(errorMessage);
        }
      })
      .fail(() => {
        this.woocommerce.displayErrorMessage(
          'Something went wrong while processing your payment.',
        );
      });
  }
}
