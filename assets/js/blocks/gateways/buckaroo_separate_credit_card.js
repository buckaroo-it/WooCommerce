import React, {useState, useEffect} from 'react';
import {__} from "@wordpress/i18n";
const SeparateCreditCard = ({ config,callbacks }) => {

    const {
        creditCardIsSecure,
        paymentInfo
    } = config;

    const {
        onSelectCc,
        onCardNameChange,
        onCardNumberChange,
        onCardMonthChange,
        onCardYearChange,
        onCardCVCChange,
        onEncryptedDataChange
    }= callbacks;

    const hiddenInputValue = paymentInfo.paymentName.replace("buckaroo_creditcard_", "");

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
                    name={`${paymentInfo.paymentName}-issuer`}
                    id={`${paymentInfo.paymentName}-issuer`}
                    className="cardHolderName input-text"
                    value={hiddenInputValue}
                />
                {creditCardIsSecure === true && (
                    <div>
                        <div className="form-row">
                            <label className="buckaroo-label" htmlFor={`${paymentInfo.paymentName}-cardname`}>
                                {__('Cardholder Name:', 'wc-buckaroo-bpe-gateway')}
                                <span className="required">*</span>
                            </label>
                            <input
                                type="text"
                                name={`${paymentInfo.paymentName}-cardname`}
                                id={`${paymentInfo.paymentName}-cardname`}
                                placeholder={__('Cardholder Name:', 'wc-buckaroo-bpe-gateway')}
                                className="cardHolderName input-text"
                                maxLength="250"
                                autoComplete="off"
                                onChange={(e) => onCardNameChange(e.target.value)}
                            />

                        </div>

                        <div className="form-row">
                            <label className="buckaroo-label" htmlFor={`${paymentInfo.paymentName}-cardnumber`}>
                                {__('Card Number:', 'wc-buckaroo-bpe-gateway')}
                                <span className="required">*</span>
                            </label>

                            <input
                                type="text"
                                name={`${paymentInfo.paymentName}-cardnumber`}
                                id={`${paymentInfo.paymentName}-cardnumber`}
                                placeholder={__('Card Number:', 'wc-buckaroo-bpe-gateway')}
                                className="cardNumber input-text"
                                maxLength="250"
                                autoComplete="off"
                                onChange={(e) => onCardNumberChange(e.target.value)}
                            />
                        </div>

                        <div className="form-row">
                            <label className="buckaroo-label" htmlFor={`${paymentInfo.paymentName}-cardmonth`}>
                                {__('Expiration Month:', 'wc-buckaroo-bpe-gateway')}
                                <span className="required">*</span>
                            </label>

                            <input
                                type="text"
                                maxLength="2"
                                name={`${paymentInfo.paymentName}-cardmonth`}
                                id={`${paymentInfo.paymentName}-cardmonth`}
                                placeholder={__('Expiration Month:', 'wc-buckaroo-bpe-gateway')}
                                className="expirationMonth input-text"
                                autoComplete="off"
                                onChange={(e) => onCardMonthChange(e.target.value)}
                            />
                        </div>

                        <div className="form-row">
                            <label className="buckaroo-label" htmlFor={`${paymentInfo.paymentName}-cardyear`}>
                                {__('Expiration Year:', 'wc-buckaroo-bpe-gateway')}
                                <span className="required">*</span>
                            </label>
                            <input
                                type="text"
                                maxLength="4"
                                name={`${paymentInfo.paymentName}-cardyear`}
                                id={`${paymentInfo.paymentName}-cardyear`}
                                placeholder={__('Expiration Year:', 'wc-buckaroo-bpe-gateway')}
                                className="expirationYear input-text"
                                autoComplete="off"
                                onChange={(e) => onCardYearChange(e.target.value)}
                            />
                        </div>

                        <div className="form-row">
                            <label className="buckaroo-label" htmlFor={`${paymentInfo.paymentName}-cardcvc`}>
                                {__('CVC:', 'wc-buckaroo-bpe-gateway')}
                                <span className="required">*</span>
                            </label>
                            <input
                                type="password"
                                maxLength="4"
                                name={`${paymentInfo.paymentName}-cardcvc`}
                                id={`${paymentInfo.paymentName}-cardcvc`}
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
                            id={`${paymentInfo.paymentName}-encrypted-data`}
                            name={`${paymentInfo.paymentName}-encrypted-data`}
                            className="encryptedCardData input-text"
                        />
                    </div>
                )}
            </div>
        </div>
    );

};

export default SeparateCreditCard;

