import BuckarooClientSideEncryption from "./creditcard-encryption-sdk";
class BuckarooValidateCreditCards {
    form = jQuery('form[name=checkout]');
    validator = BuckarooClientSideEncryption.V001;

    listen() {
        let validator = this.validator;
        let self = this;
        this.form.on('input', ".cardNumber", function(e) {
            self.toggleClasses(
                validator.validateCardNumber(e.target.value),
                e.target
            );
        });

        this.form.on('input', ".cvc", function(e) {
            self.toggleClasses(
                validator.validateCvc(e.target.value),
                e.target
            );
        });
        this.form.on('input', ".cardHolderName", function(e) {
            self.toggleClasses(
                validator.validateCardholderName(e.target.value),
                e.target
            );
        });
        this.form.on('input', ".expirationYear", function(e) {
            self.toggleClasses(
                validator.validateYear(e.target.value),
                e.target
            );
        }),

            this.form.on('input', ".expirationMonth", function(e) {
                self.toggleClasses(
                    validator.validateMonth(e.target.value),
                    e.target
                );
            });
        this.form.submit(this.submit.bind(this));
    };

    toggleClasses(valid, target) {
        target = jQuery(target);

        target.toggleClass("error", !valid);
        target.toggleClass("validated", valid);

        this.submit();
    };

    submit(e) {
        let self = this;
        let parent = jQuery('input[name="payment_method"]:checked').parent();
        let cardNumber = parent.find(".cardNumber").val();
        let cvc = parent.find(".cvc").val();
        let cardHolderName = parent.find(".cardHolderName").val();
        let expirationYear = parent.find(".expirationYear").val();
        let expirationMonth = parent.find(".expirationMonth").val();
        let cardNumberValid = BuckarooClientSideEncryption.V001.validateCardNumber(cardNumber);
        let cvcValid = BuckarooClientSideEncryption.V001.validateCvc(cvc);
        let cardHolderNameValid = BuckarooClientSideEncryption.V001.validateCardholderName(cardHolderName);
        let expirationYearValid = BuckarooClientSideEncryption.V001.validateYear(expirationYear);
        let expirationMonthValid = BuckarooClientSideEncryption.V001.validateMonth(expirationMonth);
        if (cardNumberValid && cvcValid && cardHolderNameValid && expirationYearValid && expirationMonthValid) {
            self.getEncryptedData(cardNumber, expirationYear, expirationMonth, cvc, cardHolderName, parent);
        }
    };

    getEncryptedData(cardNumber, year, month, cvc, cardholder, parent) {
        BuckarooClientSideEncryption.V001.encryptCardData(cardNumber,
            year,
            month,
            cvc,
            cardholder,
            function(encryptedCardData) {
                parent.find(".encryptedCardData").val(encryptedCardData);
            });
    }
};

export default BuckarooValidateCreditCards;