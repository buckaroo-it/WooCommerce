buckarooValidateCreditCards = {
    form: jQuery('form[name=checkout]'),
    validator: BuckarooClientSideEncryption.V001,

    listen: function() {
        var validator = this.validator;
        var self = this;
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
    },

    toggleClasses: function(valid, target) {
        target = jQuery(target);

        target.toggleClass("error", !valid);
        target.toggleClass("validated", valid);

        this.submit();
    },

    submit: function(e) {
        var parent = jQuery('input[name="payment_method"]:checked').parent();
        var cardNumber = parent.find(".cardNumber").val();
        var cvc = parent.find(".cvc").val();
        var cardHolderName = parent.find(".cardHolderName").val();
        var expirationYear = parent.find(".expirationYear").val();
        var expirationMonth = parent.find(".expirationMonth").val();
        var cardNumberValid = BuckarooClientSideEncryption.V001.validateCardNumber(cardNumber);
        var cvcValid = BuckarooClientSideEncryption.V001.validateCvc(cvc);
        var cardHolderNameValid = BuckarooClientSideEncryption.V001.validateCardholderName(cardHolderName);
        var expirationYearValid = BuckarooClientSideEncryption.V001.validateYear(expirationYear);
        var expirationMonthValid = BuckarooClientSideEncryption.V001.validateMonth(expirationMonth);
        if (cardNumberValid && cvcValid && cardHolderNameValid && expirationYearValid && expirationMonthValid) {
            console.log(this);
            this.getEncryptedData(cardNumber, expirationYear, expirationMonth, cvc, cardHolderName, parent);
        }
    },

    getEncryptedData: function(cardNumber, year, month, cvc, cardholder, parent) {
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


jQuery(function() {
    buckarooValidateCreditCards.listen();
})