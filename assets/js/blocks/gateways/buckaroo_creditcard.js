import React, { useEffect, useContext, useState } from 'react';
import DefaultDropdown from "../partials/buckaroo_creditcard_dropdown";
import { __ } from "@wordpress/i18n";
import encryptCardData from "../services/BuckarooClientSideEncryption";
import PaymentContext from '../PaymentProvider';

const CreditCard = ({
    methodName,
    gateway: { paymentMethodId, creditCardIssuers, creditCardMethod, creditCardIsSecure }
}) => {
    const { updateFormState } = useContext(PaymentContext);

    const isSingle = paymentMethodId !== 'buckaroo_creditcard';

    useEffect(() => {
        if (isSingle) {
            updateFormState(`${paymentMethodId}-creditcard-issuer`, paymentMethodId.replace("buckaroo_creditcard_", ""))
        }
    }, [isSingle, paymentMethodId])
    

    const [clientState, setClientState] = useState({
        [`${paymentMethodId}-cardname`]: '',
        [`${paymentMethodId}-cardnumber`]: '',
        [`${paymentMethodId}-cardmonth`]: '',
        [`${paymentMethodId}-cardyear`]: '',
        [`${paymentMethodId}-cardcvc`]: '',
    })

    const handleEncryption = async () => {
        try {
            const cardData = {
                cardName: clientState[`${paymentMethodId}-cardname`],
                cardNumber: clientState[`${paymentMethodId}-cardnumber`],
                cardMonth: clientState[`${paymentMethodId}-cardmonth`],
                cardYear: clientState[`${paymentMethodId}-cardyear`],
                cardCVC: clientState[`${paymentMethodId}-cardcvc`],
            };
            const encryptedData = await encryptCardData(cardData);
            updateFormState(`${paymentMethodId}-encrypted-data`, encryptedData);
        } catch (error) {
            console.error("Encryption error:", error);
        }
    };

    useEffect(() => {
        if (creditCardMethod === 'encrypt' && creditCardIsSecure === true) {
            handleEncryption();
        }
    }, [clientState]);

    const handleChange = (e) => {
        setClientState({...clientState, [`${e.target.name}`]: e.target.value});
    }

    return (
        <div>

            {!isSingle && (<p className="form-row form-row-wide">
                    <DefaultDropdown
                        paymentMethodId={paymentMethodId}
                        creditCardIssuers={creditCardIssuers}
                        handleChange={(e) => updateFormState('buckaroo_creditcard-creditcard-issuer', e.target.value)}
                    />
                </p>)}

            {creditCardMethod === 'encrypt' && creditCardIsSecure === true && (
                <div className="method--bankdata">

                    <div className="form-row">
                        <label className="buckaroo-label" htmlFor={`${paymentMethodId}-cardname`}>
                            {__('Cardholder Name:', 'wc-buckaroo-bpe-gateway')}
                            <span className="required">*</span>

                        </label>
                        <input
                            type="text"
                            name={`${paymentMethodId}-cardname`}
                            id={`${paymentMethodId}-cardname`}
                            placeholder={__('Cardholder Name:', 'wc-buckaroo-bpe-gateway')}
                            className="cardHolderName input-text"
                            maxLength="250"
                            autoComplete="off"
                            onChange={handleChange}
                        />

                    </div>

                    <div className="form-row">
                        <label className="buckaroo-label" htmlFor={`${paymentMethodId}-cardnumber`}>
                            {__('Card Number:', 'wc-buckaroo-bpe-gateway')}
                            <span className="required">*</span>
                        </label>

                        <input
                            type="text"
                            name={`${paymentMethodId}-cardnumber`}
                            id={`${paymentMethodId}-cardnumber`}
                            placeholder={__('Card Number:', 'wc-buckaroo-bpe-gateway')}
                            className="cardNumber input-text"
                            maxLength="250"
                            autoComplete="off"
                            onChange={handleChange}
                        />
                    </div>

                    <div className="form-row">
                        <label className="buckaroo-label" htmlFor={`${paymentMethodId}-cardmonth`}>
                            {__('Expiration Month:', 'wc-buckaroo-bpe-gateway')}
                            <span className="required">*</span>
                        </label>

                        <input
                            type="text"
                            maxLength="2"
                            name={`${paymentMethodId}-cardmonth`}
                            id={`${paymentMethodId}-cardmonth`}
                            placeholder={__('Expiration Month:', 'wc-buckaroo-bpe-gateway')}
                            className="expirationMonth input-text"
                            autoComplete="off"
                            onChange={handleChange}
                        />
                    </div>

                    <div className="form-row">
                        <label className="buckaroo-label" htmlFor={`${paymentMethodId}-cardyear`}>
                            {__('Expiration Year:', 'wc-buckaroo-bpe-gateway')}
                            <span className="required">*</span>
                        </label>
                        <input
                            type="text"
                            maxLength="4"
                            name={`${paymentMethodId}-cardyear`}
                            id={`${paymentMethodId}-cardyear`}
                            placeholder={__('Expiration Year:', 'wc-buckaroo-bpe-gateway')}
                            className="expirationYear input-text"
                            autoComplete="off"
                            onChange={handleChange}
                        />
                    </div>

                    <div className="form-row">
                        <label className="buckaroo-label" htmlFor={`${paymentMethodId}-cardcvc`}>
                            {__('CVC:', 'wc-buckaroo-bpe-gateway')}
                            <span className="required">*</span>
                        </label>
                        <input
                            type="password"
                            maxLength="4"
                            name={`${paymentMethodId}-cardcvc`}
                            id={`${paymentMethodId}-cardcvc`}
                            placeholder={__('CVC:', 'wc-buckaroo-bpe-gateway')}
                            className="cvc input-text"
                            autoComplete="off"
                            onChange={handleChange}
                        />
                    </div>

                    <div className="form-row form-row-wide validate-required"></div>
                    <div className="required" style={{ float: 'right' }}>*
                        {__('Required', 'wc-buckaroo-bpe-gateway')}
                    </div>
                </div>
            )}
        </div>
    );
};

export default CreditCard;

