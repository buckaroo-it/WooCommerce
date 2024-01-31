import React, {useState} from 'react';
import BirthDayField from '../partials/buckaroo_partial_birth_field'
import FinancialWarning from "../partials/buckaroo_financial_warning";
import TermsAndConditionsCheckbox from "../partials/buckaroo_terms_and_condition";
import {__} from "@wordpress/i18n";
import PhoneDropdown from "../partials/buckaroo_phone";

const AfterPayNew = ({customer_type, onCheckboxChange, billingData, onBirthdateChange, onPhoneNumberChange, onCocInput, onIdentificationNumber}) => {
        const paymentMethod = 'buckaroo-afterpaynew';

        const [isTermsAccepted, setIsTermsAccepted] = useState(false);
        let cocNumber = __('CoC-number:', 'wc-buckaroo-bpe-gateway');
        let identificationNumber = __('Identification Number:', 'wc-buckaroo-bpe-gateway');

        const handleTermsCheckboxChange = (isChecked) => {
            setIsTermsAccepted(isChecked);
            onCheckboxChange(isChecked)
        };

        const handleBirthdateChange = (date) => {
            onBirthdateChange(date);
        };
        return (
            <div>
                <PhoneDropdown paymentMethod={paymentMethod} billingData={billingData} onPhoneNumberChange={onPhoneNumberChange}></PhoneDropdown>

                {(billingData.country === 'BE' || billingData.country === 'NL') && (
                    <div>
                        <BirthDayField paymentMethod={paymentMethod} onBirthdateChange={handleBirthdateChange} />
                    </div>
                )}

                {billingData.country === 'NL' && customer_type !== 'b2c' && (
                    <p className="form-row form-row-wide validate-required">
                        <label htmlFor="buckaroo-afterpaynew-coc">
                            {cocNumber}
                            <span className="required">*</span>
                        </label>

                        <input
                            id="buckaroo-afterpaynew-coc"
                            name="buckaroo-afterpaynew-coc"
                            className="input-text"
                            type="text"
                            maxLength="250"
                            autoComplete="off"
                            onChange={(e) => onCocInput(e.target.value)}
                        />
                    </p>)}
                {billingData.country === 'FI' && (
                    <p className="form-row form-row-wide validate-required">
                        <label htmlFor="buckaroo-afterpaynew-identification-number">
                            {identificationNumber}
                            <span className="required">*</span>
                        </label>

                        <input
                            id="buckaroo-afterpaynew-identification-number"
                            name="buckaroo-afterpaynew-identification-number"
                            className="input-text"
                            type="text"
                            maxLength="250"
                            autoComplete="off"
                            onChange={(e) => onIdentificationNumber(e.target.value)}
                        />
                    </p>)}
                <TermsAndConditionsCheckbox paymentMethod={paymentMethod} onCheckboxChange={handleTermsCheckboxChange}
                                            billingData={billingData}/>
                <FinancialWarning paymentMethod={paymentMethod}/>
            </div>
        )
            ;

    }
;

export default AfterPayNew;
