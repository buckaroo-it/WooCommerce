import React, {useState, useEffect} from 'react';
import {__} from "@wordpress/i18n";

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
                                {__('Cardholder Name:', 'wc-buckaroo-bpe-gateway')}
                                <span className="required">*</span>
                            </label>
                            <input
                                type="text"
                                name={`${paymentName}-cardname`}
                                id={`${paymentName}-cardname`}
                                placeholder={__('Cardholder Name:', 'wc-buckaroo-bpe-gateway')}
                                className="cardHolderName input-text"
                                maxLength="250"
                                autoComplete="off"
                                onChange={(e) => onCardNameChange(e.target.value)}
                            />

                        </div>

                        <div className="form-row">
                            <label className="buckaroo-label" htmlFor={`${paymentName}-cardnumber`}>
                                {__('Card Number:', 'wc-buckaroo-bpe-gateway')}
                                <span className="required">*</span>
                            </label>

                            <input
                                type="text"
                                name={`${paymentName}-cardnumber`}
                                id={`${paymentName}-cardnumber`}
                                placeholder={__('Card Number:', 'wc-buckaroo-bpe-gateway')}
                                className="cardNumber input-text"
                                maxLength="250"
                                autoComplete="off"
                                onChange={(e) => onCardNumberChange(e.target.value)}
                            />
                        </div>

                        <div className="form-row">
                            <label className="buckaroo-label" htmlFor={`${paymentName}-cardmonth`}>
                                {__('Expiration Month:', 'wc-buckaroo-bpe-gateway')}
                                <span className="required">*</span>
                            </label>

                            <input
                                type="text"
                                maxLength="2"
                                name={`${paymentName}-cardmonth`}
                                id={`${paymentName}-cardmonth`}
                                placeholder={__('Expiration Month:', 'wc-buckaroo-bpe-gateway')}
                                className="expirationMonth input-text"
                                autoComplete="off"
                                onChange={(e) => onCardMonthChange(e.target.value)}
                            />
                        </div>

                        <div className="form-row">
                            <label className="buckaroo-label" htmlFor={`${paymentName}-cardyear`}>
                                {__('Expiration Year:', 'wc-buckaroo-bpe-gateway')}
                                <span className="required">*</span>
                            </label>
                            <input
                                type="text"
                                maxLength="4"
                                name={`${paymentName}-cardyear`}
                                id={`${paymentName}-cardyear`}
                                placeholder={__('Expiration Year:', 'wc-buckaroo-bpe-gateway')}
                                className="expirationYear input-text"
                                autoComplete="off"
                                onChange={(e) => onCardYearChange(e.target.value)}
                            />
                        </div>

                        <div className="form-row">
                            <label className="buckaroo-label" htmlFor={`${paymentName}-cardcvc`}>
                                {__('CVC:', 'wc-buckaroo-bpe-gateway')}
                                <span className="required">*</span>
                            </label>
                            <input
                                type="password"
                                maxLength="4"
                                name={`${paymentName}-cardcvc`}
                                id={`${paymentName}-cardcvc`}
                                placeholder={__('CVC:', 'wc-buckaroo-bpe-gateway')}
                                className="cvc input-text"
                                autoComplete="off"
                                onChange={(e) => onCardCVCChange(e.target.value)}
                            />
                        </div>

                        <div className="form-row form-row-wide validate-required"></div>
                        <div className="required" style={{float: 'right'}}>*
                            {__('Required', 'wc-buckaroo-bpe-gateway')}
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

