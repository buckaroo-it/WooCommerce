import React, {useEffect} from 'react';
import {__} from '@wordpress/i18n';
import DefaultDropdown from '../partials/buckaroo_creditcard_dropdown';
import encryptCardData from '../services/BuckarooClientSideEncryption';
import useFormData from '../hooks/useFormData';

function CreditCard({
                        onStateChange,
                        methodName,
                        gateway: {paymentMethodId, creditCardIssuers, creditCardMethod, creditCardIsSecure,}
                    }) {

    const initialState = {
        [`${paymentMethodId}-creditcard-issuer`]: '',
        [`${paymentMethodId}-cardname`]: '',
        [`${paymentMethodId}-cardnumber`]: '',
        [`${paymentMethodId}-cardmonth`]: '',
        [`${paymentMethodId}-cardyear`]: '',
        [`${paymentMethodId}-cardcvc`]: '',
        [`${paymentMethodId}-encrypted-data`]: '',
    };

    const {formState, handleChange, updateFormState} = useFormData(initialState, onStateChange);

    const handleEncryption = async () => {
        try {
            const cardData = {
                cardName: formState[`${paymentMethodId}-cardname`],
                cardNumber: formState[`${paymentMethodId}-cardnumber`],
                cardMonth: formState[`${paymentMethodId}-cardmonth`],
                cardYear: formState[`${paymentMethodId}-cardyear`],
                cardCVC: formState[`${paymentMethodId}-cardcvc`],
            };
            const encryptedData = await encryptCardData(cardData);

            updateFormState(`${paymentMethodId}-encrypted-data`, encryptedData);
        } catch (error) {
            console.error('Encryption error:', error);
        }
    };

    useEffect(() => {
        if (creditCardMethod === 'encrypt' && creditCardIsSecure === true) {
            handleEncryption();
        }
    }, [
        formState[`${paymentMethodId}-cardname`],
        formState[`${paymentMethodId}-cardnumber`],
        formState[`${paymentMethodId}-cardmonth`],
        formState[`${paymentMethodId}-cardyear`],
        formState[`${paymentMethodId}-cardcvc`],
        creditCardMethod,
        creditCardIsSecure,
    ]);

    return (
        <div>
            <p className="form-row form-row-wide">
                <DefaultDropdown
                    paymentMethodId={paymentMethodId}
                    creditCardIssuers={creditCardIssuers}
                    handleChange={handleChange}
                />
            </p>

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

                    <div className="form-row form-row-wide validate-required"/>
                    <div className="required" style={{float: 'right'}}>
                        *
                        {__('Required', 'wc-buckaroo-bpe-gateway')}
                    </div>
                </div>
            )}
        </div>
    );
}

export default CreditCard;
