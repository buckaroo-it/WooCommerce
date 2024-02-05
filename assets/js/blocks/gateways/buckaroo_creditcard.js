import React, {useState, useEffect} from 'react';
import DefaultDropdown from "../partials/buckaroo_creditcard_dropdown";
import {__} from "@wordpress/i18n";
const CreditCard = ({ config,callbacks }) => {

    const {
        creditCardIssuers,
        creditCardMethod,
        creditCardIsSecure,
    } = config;

    const {
        onCardNameChange,
        onCardNumberChange,
        onCardMonthChange,
        onCardYearChange,
        onCardCVCChange,
        onSelectCc,
        onEncryptedDataChange
    }= callbacks;

    const paymentMethod = 'buckaroo-creditcard';

    useEffect(() => {
        const handleEncryptedDataChange = (event, encryptedData) => {
            onEncryptedDataChange(encryptedData);
        };

        document.addEventListener("encryptedDataChanged", handleEncryptedDataChange);

        return () => {
            document.removeEventListener("encryptedDataChanged", handleEncryptedDataChange);
        };
    }, [onEncryptedDataChange]);

    return (
        <div>

            <p className="form-row form-row-wide">
                <DefaultDropdown paymentMethod={paymentMethod} creditCardIssuers={creditCardIssuers}
                                 onSelectCc={onSelectCc}></DefaultDropdown>
            </p>

            {creditCardMethod === 'encrypt' && creditCardIsSecure === true && (
                <div className="method--bankdata">

                    <div className="form-row">
                        <label className="buckaroo-label" htmlFor={`${paymentMethod}-cardname`}>
                            {__('Cardholder Name:', 'wc-buckaroo-bpe-gateway')}
                            <span className="required">*</span>

                        </label>
                        <input
                            type="text"
                            name={`${paymentMethod}-cardname`}
                            id={`${paymentMethod}-cardname`}
                            placeholder={__('Cardholder Name:', 'wc-buckaroo-bpe-gateway')}
                            className="cardHolderName input-text"
                            maxLength="250"
                            autoComplete="off"
                            onChange={(e) => onCardNameChange(e.target.value)}
                        />

                    </div>

                    <div className="form-row">
                        <label className="buckaroo-label" htmlFor={`${paymentMethod}-cardnumber`}>
                            {__('Card Number:', 'wc-buckaroo-bpe-gateway')}
                            <span className="required">*</span>
                        </label>

                        <input
                            type="text"
                            name={`${paymentMethod}-cardnumber`}
                            id={`${paymentMethod}-cardnumber`}
                            placeholder={__('Card Number:', 'wc-buckaroo-bpe-gateway')}
                            className="cardNumber input-text"
                            maxLength="250"
                            autoComplete="off"
                            onChange={(e) => onCardNumberChange(e.target.value)}
                        />
                    </div>

                    <div className="form-row">
                        <label className="buckaroo-label" htmlFor={`${paymentMethod}-cardmonth`}>
                            {__('Expiration Month:', 'wc-buckaroo-bpe-gateway')}
                            <span className="required">*</span>
                        </label>

                        <input
                            type="text"
                            maxLength="2"
                            name={`${paymentMethod}-cardmonth`}
                            id={`${paymentMethod}-cardmonth`}
                            placeholder={__('Expiration Month:', 'wc-buckaroo-bpe-gateway')}
                            className="expirationMonth input-text"
                            autoComplete="off"
                            onChange={(e) => onCardMonthChange(e.target.value)}
                        />
                    </div>

                    <div className="form-row">
                        <label className="buckaroo-label" htmlFor={`${paymentMethod}-cardyear`}>
                            {__('Expiration Year:', 'wc-buckaroo-bpe-gateway')}
                            <span className="required">*</span>
                        </label>
                        <input
                            type="text"
                            maxLength="4"
                            name={`${paymentMethod}-cardyear`}
                            id={`${paymentMethod}-cardyear`}
                            placeholder={__('Expiration Year:', 'wc-buckaroo-bpe-gateway')}
                            className="expirationYear input-text"
                            autoComplete="off"
                            onChange={(e) => onCardYearChange(e.target.value)}
                        />
                    </div>

                    <div className="form-row">
                        <label className="buckaroo-label" htmlFor={`${paymentMethod}-cardcvc`}>
                            {__('CVC:', 'wc-buckaroo-bpe-gateway')}
                            <span className="required">*</span>
                        </label>
                        <input
                            type="password"
                            maxLength="4"
                            name={`${paymentMethod}-cardcvc`}
                            id={`${paymentMethod}-cardcvc`}
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
                        id={`${paymentMethod}-encrypted-data`}
                        name={`${paymentMethod}-encrypted-data`}
                        className="encryptedCardData input-text"
                    />
                </div>
            )}
        </div>
    );
};

export default CreditCard;

