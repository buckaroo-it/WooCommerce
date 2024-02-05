import React, { useState } from 'react';
import BirthDayField from '../partials/buckaroo_partial_birth_field';
import FinancialWarning from '../partials/buckaroo_financial_warning';
import TermsAndConditionsCheckbox from '../partials/buckaroo_terms_and_condition';
import AfterPayB2B from '../partials/buckaroo_afterpay_b2b';
import PhoneDropdown from '../partials/buckaroo_phone';
import { __ } from '@wordpress/i18n';

const AfterPayView = ({ config,callbacks }) => {

    const {
        paymentInfo,
        type,
        billingData
    } = config;

    const {
        onPhoneNumberChange,
        onCheckboxChange,
        onBirthdateChange,
        onCocInput,
        onCompanyInput,
        onAccountName,
        onCocRegistrationChange,
        onAdditionalCheckboxChange,
    }= callbacks;

    const paymentMethod = 'buckaroo-afterpay';
    const [isAdditionalCheckboxChecked, setIsAdditionalCheckboxChecked] = useState(false);

    const handleAdditionalCheckboxChange = (isChecked) => {
        setIsAdditionalCheckboxChecked(isChecked);
        onAdditionalCheckboxChange(isChecked);
    };

    return (
        <div>
            <PhoneDropdown paymentMethod={paymentMethod} billingData={billingData} onPhoneNumberChange={onPhoneNumberChange} />
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
                        onChange={(e) => {
                            onCocRegistrationChange(e.target.value)
                        }}
                    />
                </div>
            )}
            <BirthDayField paymentMethod={paymentMethod} onBirthdateChange={onBirthdateChange} />

            {paymentInfo.b2b === 'enable' && paymentInfo.type === 'afterpaydigiaccept' && (
                <div>
                    <div className="form-row form-row-wide validate-required">
                        <label htmlFor="buckaroo-afterpay-b2b">
                            {__('Checkout for company', 'wc-buckaroo-bpe-gateway')}
                            <input
                                id="buckaroo-afterpay-b2b"
                                name="buckaroo-afterpay-b2b"
                                type="checkbox"
                                onChange={(e) => handleAdditionalCheckboxChange(e.target.checked)}
                            />
                        </label>
                    </div>
                    {isAdditionalCheckboxChecked && <AfterPayB2B onCocInput={onCocInput} onCompanyInput={onCompanyInput} onAccountName={onAccountName} />}
                </div>
            )}
            <TermsAndConditionsCheckbox
                paymentMethod={paymentMethod}
                onCheckboxChange={onCheckboxChange}
                billingData={billingData}
            />
            <FinancialWarning paymentMethod={paymentMethod} />
        </div>
    );
};

export default AfterPayView;
