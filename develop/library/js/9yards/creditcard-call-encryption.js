(function(jQuery) {

    var checkCardnumberexists = setInterval(function() {
        if(typeof(jQuery(".cardNumber").html()) != 'undefined' && !jQuery(".cardNumber").hasClass("hasRun")) {

            jQuery(".cardNumber").on('input', function (e) {
                if (!BuckarooClientSideEncryption.V001.validateCardNumber(e.target.value)) {
                    jQuery(".cardNumber").addClass("error");
                    jQuery(".cardNumber").removeClass("validated");
                } else {
                    jQuery(".cardNumber").addClass("validated");
                    jQuery(".cardNumber").removeClass("error");
                }
                submit();
            });

            jQuery(".cvc").on('input', function (e) {
                if (!BuckarooClientSideEncryption.V001.validateCvc(e.target.value)) {
                    jQuery(".cvc").addClass("error");
                    jQuery(".cvc").removeClass("validated");
                } else {
                    jQuery(".cvc").addClass("validated");
                    jQuery(".cvc").removeClass("error");
                }
                submit();
            });
            jQuery(".cardHolderName").on('input', function (e) {
                if (!BuckarooClientSideEncryption.V001.validateCardholderName(e.target.value)) {
                    jQuery(".cardHolderName").addClass("error");
                    jQuery(".cardHolderName").removeClass("validated");
                } else {
                    jQuery(".cardHolderName").addClass("validated");
                    jQuery(".cardHolderName").removeClass("error");
                }
                submit();
            });
            jQuery(".expirationYear").on('input', function (e) {
                if (!BuckarooClientSideEncryption.V001.validateYear(e.target.value)) {
                    jQuery(".expirationYear").addClass("error");
                    jQuery(".expirationYear").removeClass("validated");
                } else {
                    jQuery(".expirationYear").addClass("validated");
                    jQuery(".expirationYear").removeClass("error");
                }
                submit();
            });

            jQuery(".expirationMonth").on('input', function (e) {
                if (!BuckarooClientSideEncryption.V001.validateMonth(e.target.value)) {
                    jQuery(".expirationMonth").addClass("error");
                    jQuery(".expirationMonth").removeClass("validated");
                } else {
                    jQuery(".expirationMonth").addClass("validated");
                    jQuery(".expirationMonth").removeClass("error");
                }
                submit();
            });

            jQuery('form[name=checkout]').submit(submit);

            jQuery(".cardNumber").addClass("hasRun");
        }
    }, 100);


    var submit = function(e) {
        var cardNumber = jQuery(".cardNumber").val();
        var cvc = jQuery(".cvc").val();
        var cardHolderName = jQuery(".cardHolderName").val();
        var expirationYear = jQuery(".expirationYear").val();
        var expirationMonth = jQuery(".expirationMonth").val();
        var cardNumberValid = BuckarooClientSideEncryption.V001.validateCardNumber(cardNumber);
        var cvcValid = BuckarooClientSideEncryption.V001.validateCvc(cvc);
        var cardHolderNameValid = BuckarooClientSideEncryption.V001.validateCardholderName(cardHolderName);
        var expirationYearValid = BuckarooClientSideEncryption.V001.validateYear(expirationYear);
        var expirationMonthValid = BuckarooClientSideEncryption.V001.validateMonth(expirationMonth);
        if (cardNumberValid && cvcValid && cardHolderNameValid && expirationYearValid && expirationMonthValid) {
            getEncryptedData(cardNumber, expirationYear, expirationMonth, cvc, cardHolderName);
        } else {
            // Show user that data is incomplete
        }
    }

    var getEncryptedData = function(cardNumber, year, month, cvc, cardholder) {
        BuckarooClientSideEncryption.V001.encryptCardData(cardNumber,
            year,
            month,
            cvc,
            cardholder,
            function(encryptedCardData) {
                jQuery(".encryptedCardData").val(encryptedCardData);
                console.log(encryptedCardData);
            });
    }
    
})(jQuery);