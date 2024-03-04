import React, {useState} from 'react';
import BirthDayField from '../partials/buckaroo_partial_birth_field';
import FinancialWarning from '../partials/buckaroo_financial_warning';
import TermsAndConditionsCheckbox from '../partials/buckaroo_terms_and_condition';
import AfterPayB2B from '../partials/buckaroo_afterpay_b2b';
import PhoneDropdown from '../partials/buckaroo_phone';
import {__} from '@wordpress/i18n';
import useFormData from '../hooks/useFormData';

const AfterPayView = ({onStateChange, methodName, gateway: {type, b2b}, billing}) => {
    const initialState = {
        [`${methodName}-phone`]: billing?.phone || '',
        [`${methodName}-birthdate`]: '',
        [`${methodName}-b2b`]: '',
        [`${methodName}-company-coc-registration`]: '',
        [`${methodName}-company-name`]: '',
        [`${methodName}-accept`]: '',
    };

    const [formState, handleChange, updateFormState] = useFormData(initialState, onStateChange);

    const handleTermsChange = (value) => {
        updateFormState(`${methodName}-accept`, value);
    };

    const handleBirthDayChange = (value) => {
        updateFormState(`${methodName}-birthdate`, value);
    };

    const handlePhoneChange = (value) => {
        updateFormState(`${methodName}-phone`, value);
    };


    const [isAdditionalCheckboxChecked, setIsAdditionalCheckboxChecked] = useState(false);

    const handleAdditionalCheckboxChange = (isChecked) => {
        setIsAdditionalCheckboxChecked(isChecked);
        updateFormState(`${methodName}-b2b`, isChecked ? 'ON' : 'OFF');
    };

    return (
        <div>
            <PhoneDropdown paymentMethod={methodName} formState={formState} handlePhoneChange={handlePhoneChange}/>
            {type === 'afterpayacceptgiro' && (
                <div className="form-row form-row-wide validate-required">
                    <label htmlFor="buckaroo-afterpay-company-coc-registration">
                        {__('IBAN:', 'wc-buckaroo-bpe-gateway')}
                        <span className="required">*</span>
                    </label>

                    <input
                        id="buckaroo-afterpay-company-coc-registration"
                        name="buckaroo-afterpay-company-coc-registration"
                        className="input-text"
                        type="text"
                        onChange={handleChange}
                    />
                </div>
            )}

            {!isAdditionalCheckboxChecked &&
                <BirthDayField paymentMethod={methodName} handleBirthDayChange={handleBirthDayChange}/>}

            {b2b === 'enable' && type === 'afterpaydigiaccept' && (
                <div>
                    <div className="form-row form-row-wide validate-required">
                        <label htmlFor="buckaroo-afterpay-b2b">
                            {__('Checkout for company', 'wc-buckaroo-bpe-gateway')}
                            <input
                                id="buckaroo-afterpay-b2b"
                                name="buckaroo-afterpay-b2b"
                                type="checkbox"
                                onChange={handleAdditionalCheckboxChange}
                            />
                        </label>
                    </div>
                    {isAdditionalCheckboxChecked && <AfterPayB2B handleChange={handleChange}/>}
                </div>
            )}
            <TermsAndConditionsCheckbox
                paymentMethod={methodName}
                handleTermsChange={handleTermsChange}
                billingData={billing}
                b2b={b2b}
            />
            <FinancialWarning paymentMethod={methodName}/>
        </div>
    );
};

export default AfterPayView;
