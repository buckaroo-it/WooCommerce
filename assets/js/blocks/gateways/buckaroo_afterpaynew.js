import React, { useState } from 'react';
import BirthDayField from '../partials/buckaroo_partial_birth_field'
import FinancialWarning from "../partials/buckaroo_financial_warning";
import AfterPayB2B from "../partials/buckaroo_afterpay_b2b";
import TermsAndConditionsCheckbox from "../partials/buckaroo_terms_and_condition";

const AfterPayNew = ({onCheckboxChange,onBirthdateChange}) => {
    const paymentMethod = 'buckaroo-afterpaynew';
    const labelText = 'Accept Riverty | AfterPay conditions:';
    const termsUrl = 'buckaroo-afterpay';

    const [isTermsAccepted, setIsTermsAccepted] = useState(false);
    const handleTermsCheckboxChange = (isChecked) => {
        setIsTermsAccepted(isChecked);
        onCheckboxChange(isChecked)
    };

    const handleBirthdateChange = (date) => {
        onBirthdateChange(date);
    };

    return (
        <div>
            <BirthDayField paymentMethod={paymentMethod} onBirthdateChange={handleBirthdateChange}/>
            <TermsAndConditionsCheckbox paymentMethod={paymentMethod} onCheckboxChange={handleTermsCheckboxChange}  labelText={labelText} termsUrl={termsUrl}/>
            <FinancialWarning paymentMethod={paymentMethod} />
        </div>
    );

};

export default AfterPayNew;
