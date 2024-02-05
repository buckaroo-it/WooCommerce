import React, { useState } from 'react';
import BirthDayField from '../partials/buckaroo_partial_birth_field';
import FinancialWarning from '../partials/buckaroo_financial_warning';
import TermsAndConditionsCheckbox from '../partials/buckaroo_terms_and_condition';
import { __ } from '@wordpress/i18n';
import PhoneDropdown from '../partials/buckaroo_phone';

const AfterPayNew = ({ config,callbacks }) => {

    const {
        paymentInfo,
        billingData,
        customer_type
    } = config;

    const {
        onPhoneNumberChange,
        onCheckboxChange,
        onBirthdateChange,
        onCocInput
    }= callbacks;

    const paymentMethod = 'buckaroo-afterpaynew';
    const [isTermsAccepted, setIsTermsAccepted] = useState(false);

    const handleChange = (field, value) => {
        switch (field) {
            case 'terms':
                setIsTermsAccepted(value);
                onCheckboxChange(value);
                break;
            case 'coc':
                onCocInput(value);
                break;
            case 'identificationNumber':
                onIdentificationNumber(value);
                break;
            default:
                break;
        }
    };

    return (
        <div>
            <PhoneDropdown paymentMethod={paymentMethod} billingData={billingData} onPhoneNumberChange={onPhoneNumberChange} />

            {(billingData.country === 'BE' || billingData.country === 'NL') && (
                <div>
                    <BirthDayField paymentMethod={paymentMethod} onBirthdateChange={onBirthdateChange} />
                </div>
            )}

            {billingData.country === 'NL' && customer_type !== 'b2c' && (
                <p className="form-row form-row-wide validate-required">
                    <label htmlFor="buckaroo-afterpaynew-coc">
                        {__('CoC-number:', 'wc-buckaroo-bpe-gateway')}
                        <span className="required">*</span>
                    </label>
                    <input
                        id="buckaroo-afterpaynew-coc"
                        name="buckaroo-afterpaynew-coc"
                        className="input-text"
                        type="text"
                        maxLength="250"
                        autoComplete="off"
                        onChange={(e) => handleChange('coc', e.target.value)}
                    />
                </p>
            )}

            {billingData.country === 'FI' && (
                <p className="form-row form-row-wide validate-required">
                    <label htmlFor="buckaroo-afterpaynew-identification-number">
                        {__('Identification Number:', 'wc-buckaroo-bpe-gateway')}
                        <span className="required">*</span>
                    </label>
                    <input
                        id="buckaroo-afterpaynew-identification-number"
                        name="buckaroo-afterpaynew-identification-number"
                        className="input-text"
                        type="text"
                        maxLength="250"
                        autoComplete="off"
                        onChange={(e) => handleChange('identificationNumber', e.target.value)}
                    />
                </p>
            )}

            <TermsAndConditionsCheckbox
                paymentMethod={paymentMethod}
                onCheckboxChange={(isChecked) => handleChange('terms', isChecked)}
                billingData={billingData}
            />

            <FinancialWarning paymentMethod={paymentMethod} />
        </div>
    );
};

export default AfterPayNew;
