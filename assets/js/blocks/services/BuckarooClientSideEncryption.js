import BuckarooClientSideEncryption from '../../checkout/creditcard-encryption-sdk';

const encryptCardData = (cardDetails) => {
  const {
    cardNumber, cardYear, cardMonth, cardCVC, cardName,
  } = cardDetails;
  const validator = BuckarooClientSideEncryption.V001;

  return new Promise((resolve) => {
    if (validator.validateCardNumber(cardNumber)
            && validator.validateCvc(cardCVC)
            && validator.validateCardholderName(cardName)
            && validator.validateYear(cardYear)
            && validator.validateMonth(cardMonth)) {
      BuckarooClientSideEncryption.V001.encryptCardData(cardNumber, cardYear, cardMonth, cardCVC, cardName, (encryptedCardData) => {
        resolve(encryptedCardData);
      });
    }
  });
};

export default encryptCardData;
