import BuckarooClientSideEncryption from '../../checkout/creditcard-encryption-sdk';

const encryptCardData = (cardDetails, onEncryptedDataChange) => {
    const { cardNumber, cardYear, cardMonth, cardCVC, cardName } = cardDetails;
    const validator = BuckarooClientSideEncryption.V001;

    if (validator.validateCardNumber(cardNumber) &&
        validator.validateCvc(cardCVC) &&
        validator.validateCardholderName(cardName) &&
        validator.validateYear(cardYear) &&
        validator.validateMonth(cardMonth)) {

        // Perform the encryption
        BuckarooClientSideEncryption.V001.encryptCardData(
            cardNumber, cardYear, cardMonth, cardCVC, cardName,
            (encryptedCardData) => {
                onEncryptedDataChange(encryptedCardData);
            }
        );
    }
};

export default encryptCardData;
