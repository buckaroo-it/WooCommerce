import React, {useState} from 'react';
import BirthDayField from '../partials/buckaroo_partial_birth_field';
import FinancialWarning from "../partials/buckaroo_financial_warning";
import TermsAndConditionsCheckbox from "../partials/buckaroo_terms_and_condition";
import AfterPayB2B from '../partials/buckaroo_afterpay_b2b';
import PhoneDropdown from "../partials/buckaroo_phone";
import {__} from "@wordpress/i18n";

const AfterPayView = ({
                          b2b,
                          type,
                          billingData,
                          onPhoneNumberChange,
                          onCheckboxChange,
                          onBirthdateChange,
                          onCocInput,
                          onCompanyInput,
                          onAccountName,
                          onCocRegistrationChange,
                          onAdditionalCheckboxChange
                      }) => {
    const paymentMethod = 'buckaroo-afterpay';
    const [isAdditionalCheckboxChecked, setIsAdditionalCheckboxChecked] = useState(false);
    let iban = __('IBAN:', 'wc-buckaroo-bpe-gateway');
    const [isTermsAccepted, setIsTermsAccepted] = useState(false);

    const handleTermsCheckboxChange = (isChecked) => {
        setIsTermsAccepted(isChecked);
        onCheckboxChange(isChecked);
    };

    const handleCocInput = (input) => {
        onCocInput(input);
    };
    const handleCompanyInput = (input) => {
        onCompanyInput(input);
    };

    const handleAccount = (input) => {
        onAccountName(input);
    };

    const handleAdditionalCheckboxChange = (isChecked) => {
        setIsAdditionalCheckboxChecked(isChecked);
        onAdditionalCheckboxChange(isChecked);
    };

    return (
        <div>
            <PhoneDropdown paymentMethod={paymentMethod} billingData={billingData}
                           onPhoneNumberChange={onPhoneNumberChange}></PhoneDropdown>
            {type === 'afterpayacceptgiro' && (
                <div className="form-row form-row-wide validate-required">
                    <label htmlFor="buckaroo-afterpay-company-coc-registration">
                        {iban}
                        <span className="required">*</span>
                    </label>

                    <input
                        id="buckaroo-afterpay-company-coc-registration"
                        name="buckaroo-afterpay-company-coc-registration"
                        className="input-text"
                        type="text"
                        onChange={(e) => {onCocRegistrationChange(e.target.value)}}
                    />
                </div>
            )
            }
            <BirthDayField paymentMethod={paymentMethod} onBirthdateChange={onBirthdateChange}/>

            {b2b === 'enable' && type === 'afterpaydigiaccept' && (
                <div>
                    <div className="form-row form-row-wide validate-required">
                        <label htmlFor="buckaroo-afterpay-b2b">
                            Checkout for company
                            <input
                                id="buckaroo-afterpay-b2b"
                                name="buckaroo-afterpay-b2b"
                                type="checkbox"
                                onChange={(e) => handleAdditionalCheckboxChange(e.target.checked)}
                            />
                        </label>
                    </div>
                    {isAdditionalCheckboxChecked &&
                        <AfterPayB2B onCocInput={handleCocInput} onCompanyInput={handleCompanyInput}
                                     onAccountName={handleAccount}/>}
                </div>
            )}
            <TermsAndConditionsCheckbox paymentMethod={paymentMethod} onCheckboxChange={handleTermsCheckboxChange}
                                        billingData={billingData}/>
            <FinancialWarning paymentMethod={paymentMethod}/>
        </div>
    );
};

export default AfterPayView;
