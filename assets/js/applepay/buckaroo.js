import Woocommerce from './woocommerce';

export default class Buckaroo {
  constructor() {
    this.woocommerce = new Woocommerce();
  }

  createTransaction(paymentData, amount, selectedShippingMethod, items) {
    jQuery.ajax({
      url: '/?wc-api=WC_Gateway_Buckaroo_applepay-create-transaction',
      method: 'post',
      data: {
        selectedShippingMethod,
        paymentData,
        amount,
        items,
      },
      dataType: 'json',
      async: false,
    })
      .done((buckarooResponse) => {
        if (buckarooResponse.result === 'success') {
          window.location.replace(buckarooResponse.redirect);
        } else {
          let errorMessage = 'Something went wrong while processing your payment.';
          if (buckarooResponse.message) {
            errorMessage = buckarooResponse.message;
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
