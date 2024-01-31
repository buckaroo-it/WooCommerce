import React, {useState, useEffect} from 'react';

const SeparateCreditCard = ({
                                onCardNameChange,
                                creditCardIsSecure,
                                paymentName,
                                onSelectCc,
                                onCardNumberChange,
                                onCardMonthChange,
                                onCardYearChange,
                                onCardCVCChange,
                                onEncryptedDataChange
                            }) => {
    const hiddenInputValue = paymentName.replace("buckaroo_creditcard_", "");

    useEffect(() => {
        onSelectCc(hiddenInputValue);
    }, [hiddenInputValue, onSelectCc]);

    useEffect(() => {
        const handleEncryptedDataChange = (event, encryptedData) => {
            onEncryptedDataChange(encryptedData);
        };

        jQuery(document).on("encryptedDataChanged", handleEncryptedDataChange);

        return () => {
            jQuery(document).off("encryptedDataChanged", handleEncryptedDataChange);
        };
    }, [onEncryptedDataChange]);

    return (
        <div>

            <div className="method--bankdata">

                <input
                    type="hidden"
                    name={`${paymentName}-issuer`}
                    id={`${paymentName}-issuer`}
                    className="cardHolderName input-text"
                    value={hiddenInputValue}
                />
                {creditCardIsSecure === true && (
                    <div>
                        <div className="form-row">
                            <label className="buckaroo-label" htmlFor={`${paymentName}-cardname`}>
                                Cardholder Name:
                                <span className="required">*</span>

                            </label>
                            <input
                                type="text"
                                name={`${paymentName}-cardname`}
                                id={`${paymentName}-cardname`}
                                placeholder="Cardholder Name:"
                                className="cardHolderName input-text"
                                maxLength="250"
                                autoComplete="off"
                                onChange={(e) => onCardNameChange(e.target.value)}
                            />

                        </div>

                        <div className="form-row">
                            <label className="buckaroo-label" htmlFor={`${paymentName}-cardnumber`}>
                                Card Number:
                                <span className="required">*</span>
                            </label>

                            <input
                                type="text"
                                name={`${paymentName}-cardnumber`}
                                id={`${paymentName}-cardnumber`}
                                placeholder="Card Number:"
                                className="cardNumber input-text"
                                maxLength="250"
                                autoComplete="off"
                                onChange={(e) => onCardNumberChange(e.target.value)}
                            />
                        </div>

                        <div className="form-row">
                            <label className="buckaroo-label" htmlFor={`${paymentName}-cardmonth`}>
                                Expiration Month:
                                <span className="required">*</span>
                            </label>

                            <input
                                type="text"
                                maxLength="2"
                                name={`${paymentName}-cardmonth`}
                                id={`${paymentName}-cardmonth`}
                                placeholder="Expiration Month:"
                                className="expirationMonth input-text"
                                autoComplete="off"
                                onChange={(e) => onCardMonthChange(e.target.value)}
                            />
                        </div>

                        <div className="form-row">
                            <label className="buckaroo-label" htmlFor={`${paymentName}-cardyear`}>
                                Expiration Year:
                                <span className="required">*</span>
                            </label>
                            <input
                                type="text"
                                maxLength="4"
                                name={`${paymentName}-cardyear`}
                                id={`${paymentName}-cardyear`}
                                placeholder="Expiration Year:"
                                className="expirationYear input-text"
                                autoComplete="off"
                                onChange={(e) => onCardYearChange(e.target.value)}
                            />
                        </div>

                        <div className="form-row">
                            <label className="buckaroo-label" htmlFor={`${paymentName}-cardcvc`}>
                                CVC:
                                <span className="required">*</span>
                            </label>
                            <input
                                type="password"
                                maxLength="4"
                                name={`${paymentName}-cardcvc`}
                                id={`${paymentName}-cardcvc`}
                                placeholder="CVC:"
                                className="cvc input-text"
                                autoComplete="off"
                                onChange={(e) => onCardCVCChange(e.target.value)}
                            />
                        </div>

                        <div className="form-row form-row-wide validate-required"></div>
                        <div className="required" style={{float: 'right'}}>*
                            Required
                        </div>
                        <input
                            type="hidden"
                            id={`${paymentName}-encrypted-data`}
                            name={`${paymentName}-encrypted-data`}
                            className="encryptedCardData input-text"
                        />
                    </div>
                )}
            </div>
        </div>
    );

};

export default SeparateCreditCard;

