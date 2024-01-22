import React, {useState} from 'react';
import BirthDayField from '../partials/buckaroo_partial_birth_field'
import FinancialWarning from "../partials/buckaroo_financial_warning";
import TermsAndConditionsCheckbox from "../partials/buckaroo_terms_and_condition";
import {__} from "@wordpress/i18n";

const AfterPayNew = ({customer_type, onCheckboxChange, billingData, onBirthdateChange}) => {
        const paymentMethod = 'buckaroo-afterpaynew';

        const [isTermsAccepted, setIsTermsAccepted] = useState(false);
        let cocNumber = __('CoC-number:', 'wc-buckaroo-bpe-gateway');
        let identificationNumber = __('Identification Number:', 'wc-buckaroo-bpe-gateway');
        let phoneNumber = __('Phone Number:', 'wc-buckaroo-bpe-gateway');

        const handleTermsCheckboxChange = (isChecked) => {
            setIsTermsAccepted(isChecked);
            onCheckboxChange(isChecked)
        };

        const handleBirthdateChange = (date) => {
            onBirthdateChange(date);
        };
        return (
            <div>
                {(billingData.country === 'BE' || billingData.country === 'NL') && (
                    <div>
                        <BirthDayField paymentMethod={paymentMethod} onBirthdateChange={handleBirthdateChange} />

                        <p className="form-row validate-required">
                            <label htmlFor="buckaroo-afterpaynew-phone">
                                {phoneNumber}
                                <span className="required">*</span>
                            </label>
                            <input
                                id="buckaroo-afterpaynew-phone"
                                name="buckaroo-afterpaynew-phone"
                                className="input-text"
                                type="tel"
                                autoComplete="off"
                                value=""
                            />
                        </p>

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
                            value=""/>
                    </p>)}
                {billingData.country === 'FI' && (
                    <p className="form-row form-row-wide validate-required">
                        <label htmlFor="buckaroo-afterpaynew-IdentificationNumber">
                            {identificationNumber}
                            <span className="required">*</span>
                        </label>

                        <input
                            id="buckaroo-afterpaynew-IdentificationNumber"
                            name="buckaroo-afterpaynew-IdentificationNumber"
                            className="input-text"
                            type="text"
                            maxLength="250"
                            autoComplete="off"
                            value=""/>
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
