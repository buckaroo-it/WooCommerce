import React, {useState} from 'react';
import BirthDayField from '../partials/buckaroo_partial_birth_field';
import FinancialWarning from "../partials/buckaroo_financial_warning";
import TermsAndConditionsCheckbox from "../partials/buckaroo_terms_and_condition";
import AfterPayB2B from '../partials/buckaroo_afterpay_b2b';
import PhoneDropdown from "../partials/buckaroo_phone";

const AfterPayView = ({
                          b2b,
                          billingData,
                          onPhoneNumberChange,
                          onCheckboxChange,
                          onBirthdateChange,
                          onCocInput,
                          onCompanyInput,
                          onAccountName
                      }) => {
    const paymentMethod = 'buckaroo-afterpay';
    const [isTermsAccepted, setIsTermsAccepted] = useState(false);
    const [isAdditionalCheckboxChecked, setIsAdditionalCheckboxChecked] = useState(false);

    const handleTermsCheckboxChange = (isChecked) => {
        setIsTermsAccepted(isChecked);
        onCheckboxChange(isChecked);
    };

    const handleBirthdateChange = (date) => {
        onBirthdateChange(date);
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
    };

    return (<div>
        <PhoneDropdown paymentMethod={paymentMethod} billingData={billingData} onPhoneNumberChange={onPhoneNumberChange}></PhoneDropdown>

        {b2b === 'enable' && (<div>
            <div className="form-row form-row-wide validate-required">
                <label htmlFor="buckaroo-afterpay-b2b">
                    Checkout for company
                    <input
                        id="buckaroo-afterpay-b2b"
                        name="buckaroo-afterpay-b2b"
                        type="checkbox" value=""
                        onChange={(e) => handleAdditionalCheckboxChange(e.target.checked)}
                    />
                </label>
            </div>
            {isAdditionalCheckboxChecked &&
                <AfterPayB2B onCocInput={handleCocInput} onCompanyInput={handleCompanyInput}
                             onAccountName={handleAccount}/>}
        </div>)}
        {b2b === 'disable' && (
            <BirthDayField paymentMethod={paymentMethod} onBirthdateChange={handleBirthdateChange}/>)}
        <TermsAndConditionsCheckbox paymentMethod={paymentMethod} onCheckboxChange={handleTermsCheckboxChange}
                                    billingData={billingData}/>
        <FinancialWarning paymentMethod={paymentMethod}/>
    </div>);
};

export default AfterPayView;