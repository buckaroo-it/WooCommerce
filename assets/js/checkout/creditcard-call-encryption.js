import BuckarooClientSideEncryption from './creditcard-encryption-sdk';

class BuckarooValidateCreditCards {
  constructor() {
    this.form = jQuery('form[name=checkout]');
    this.validator = BuckarooClientSideEncryption.V001;
  }

  listen() {
    const { validator } = this;
    const self = this;
    this.form.on('input', '.cardNumber', (e) => {
      self.toggleClasses(
        validator.validateCardNumber(e.target.value),
        e.target,
      );
    });

    this.form.on('input', '.cvc', (e) => {
      self.toggleClasses(
        validator.validateCvc(e.target.value),
        e.target,
      );
    });

    this.form.on('input', '.cardHolderName', (e) => {
      self.toggleClasses(
        validator.validateCardholderName(e.target.value),
        e.target,
      );
    });

    this.form.on('input', '.expirationYear', (e) => {
      self.toggleClasses(
        validator.validateYear(e.target.value),
        e.target,
      );
    });

    this.form.on('input', '.expirationMonth', (e) => {
      self.toggleClasses(
        validator.validateMonth(e.target.value),
        e.target,
      );
    });

    this.form.submit(this.submit.bind(this));
  }

  toggleClasses(valid, target) {
    const $target = jQuery(target);

    $target.toggleClass('error', !valid);
    $target.toggleClass('validated', valid);

    this.submit();
  }

  submit() {
    const parent = jQuery('input[name="payment_method"]:checked').parent();
    const cardNumber = parent.find('.cardNumber').val();
    const cvc = parent.find('.cvc').val();
    const cardHolderName = parent.find('.cardHolderName').val();
    const expirationYear = parent.find('.expirationYear').val();
    const expirationMonth = parent.find('.expirationMonth').val();
    const cardNumberValid = BuckarooClientSideEncryption.V001.validateCardNumber(cardNumber);
    const cvcValid = BuckarooClientSideEncryption.V001.validateCvc(cvc);
    const cardHolderNameValid = BuckarooClientSideEncryption.V001.validateCardholderName(cardHolderName);
    const expirationYearValid = BuckarooClientSideEncryption.V001.validateYear(expirationYear);
    const expirationMonthValid = BuckarooClientSideEncryption.V001.validateMonth(expirationMonth);
    if (cardNumberValid && cvcValid && cardHolderNameValid && expirationYearValid && expirationMonthValid) {
      this.constructor.getEncryptedData(cardNumber, expirationYear, expirationMonth, cvc, cardHolderName, parent);
    }
  }

  static getEncryptedData(cardNumber, year, month, cvc, cardholder, parent) {
    BuckarooClientSideEncryption.V001.encryptCardData(
      cardNumber,
      year,
      month,
      cvc,
      cardholder,
      (encryptedCardData) => {
        parent.find('.encryptedCardData').val(encryptedCardData);
      },
    );
  }
}

export default BuckarooValidateCreditCards;
