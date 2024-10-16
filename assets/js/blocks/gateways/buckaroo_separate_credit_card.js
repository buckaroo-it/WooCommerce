import React, {useEffect} from 'react';
import {__} from '@wordpress/i18n';
import encryptCardData from '../services/BuckarooClientSideEncryption';
import useFormData from '../hooks/useFormData';

function SeparateCreditCard({onStateChange, gateway}) {
    const {paymentMethodId, creditCardMethod, creditCardIsSecure} = gateway;
    const initialState = {
        [`${paymentMethodId}-creditcard-issuer`]: paymentMethodId.replace('buckaroo_creditcard_', ''),
        [`${paymentMethodId}-cardname`]: '',
        [`${paymentMethodId}-cardnumber`]: '',
        [`${paymentMethodId}-cardmonth`]: '',
        [`${paymentMethodId}-cardyear`]: '',
        [`${paymentMethodId}-cardcvc`]: '',
        [`${paymentMethodId}-encrypted-data`]: '',
    };
    // Destructure the object returned by useFormData
    const {formState, handleChange, updateFormState} = useFormData(initialState, onStateChange);

    useEffect(() => {
        updateFormState(`${paymentMethodId}-creditcard-issuer`, initialState[`${paymentMethodId}-creditcard-issuer`]);
    }, [`${paymentMethodId}-creditcard-issuer`]);

    const handleEncryption = async () => {
        if (creditCardMethod !== 'encrypt' || !creditCardIsSecure) return;

        try {
            const encryptedData = await encryptCardData({
                cardName: formState[`${paymentMethodId}-cardname`],
                cardNumber: formState[`${paymentMethodId}-cardnumber`],
                cardMonth: formState[`${paymentMethodId}-cardmonth`],
                cardYear: formState[`${paymentMethodId}-cardyear`],
                cardCVC: formState[`${paymentMethodId}-cardcvc`],
            });
            onStateChange({...formState, [`${paymentMethodId}-encrypted-data`]: encryptedData});
        } catch (error) {
            console.error('Encryption error:', error);
        }
    };

    useEffect(() => {
        handleEncryption();
    }, [
        formState[`${paymentMethodId}-cardname`],
        formState[`${paymentMethodId}-cardnumber`],
        formState[`${paymentMethodId}-cardmonth`],
        formState[`${paymentMethodId}-cardyear`],
        formState[`${paymentMethodId}-cardcvc`],
    ]);

    return (
        <div>
            <div className="method--bankdata">
                <input
                    type="hidden"
                    name={`${paymentMethodId}-creditcard-issuer`}
                    id={`${paymentMethodId}-creditcard-issuer`}
                    className="cardHolderName input-text"
                    value={formState[`${paymentMethodId}-creditcard-issuer`]}
                />
                {creditCardIsSecure === true && (
                    <div>
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
        </div>
    );
}

export default SeparateCreditCard;
