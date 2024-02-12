import React, {useState, useEffect} from 'react';
import DefaultDropdown from "../partials/buckaroo_creditcard_dropdown";
import {__} from "@wordpress/i18n";
import encryptCardData from "../services/BuckarooClientSideEncryption";

const CreditCard = ({ config, callbacks }) => {
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
        onEncryptedDataChange,
    } = callbacks;

    const [cardNumber, setCardNumber] = useState('');
    const [cardName, setCardName] = useState('');
    const [cardMonth, setCardMonth] = useState('');
    const [cardYear, setCardYear] = useState('');
    const [cardCVC, setCardCVC] = useState('');

    const paymentMethod = 'buckaroo-creditcard';

    const handleEncryption = async () => {
        try {
            const encryptedData = await encryptCardData({
                cardName,
                cardNumber,
                cardMonth,
                cardYear,
                cardCVC,
            });
            onEncryptedDataChange(encryptedData);
        } catch (error) {
            console.error("Encryption error:", error);
        }
    };

    useEffect(() => {
        if (creditCardMethod === 'encrypt' && creditCardIsSecure === true) {
            handleEncryption();
        }
    }, [cardNumber, cardName, cardMonth, cardYear, cardCVC, creditCardMethod, onEncryptedDataChange, creditCardIsSecure]);

    return (
        <div>

            <p className="form-row form-row-wide">
                <DefaultDropdown
                    paymentMethod={paymentMethod}
                    creditCardIssuers={creditCardIssuers}
                    onSelectCc={(selectedIssuer) => {
                        onSelectCc(selectedIssuer);
                    }}
                />
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
                            onChange={(e) => {
                                setCardName(e.target.value);
                                onCardNameChange(e.target.value);
                            }}
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
                            onChange={(e) => {
                                setCardNumber(e.target.value);
                                onCardNumberChange(e.target.value);
                            }}
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
                            onChange={(e) => {
                                setCardMonth(e.target.value);
                                onCardMonthChange(e.target.value);
                            }}
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
                            onChange={(e) => {
                                setCardYear(e.target.value);
                                onCardYearChange(e.target.value);
                            }}
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
                            onChange={(e) => {
                                setCardCVC(e.target.value);
                                onCardCVCChange(e.target.value);
                            }}
                        />
                    </div>

                    <div className="form-row form-row-wide validate-required"></div>
                    <div className="required" style={{float: 'right'}}>*
                        {__('Required', 'wc-buckaroo-bpe-gateway')}
                    </div>
                </div>
            )}
        </div>
    );
};

export default CreditCard;

