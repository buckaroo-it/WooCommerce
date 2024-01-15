import React, { useState, useEffect } from 'react';
import BirthDayField from '../partials/buckaroo_partial_birth_field'
import FinancialWarning from "../partials/buckaroo_financial_warning";
import TermsAndConditionsCheckbox from "../partials/buckaroo_terms_and_condition";

const AfterPayView = ({onCheckboxChange,onBirthdateChange}) => {
    const paymentMethod = 'buckaroo-afterpay';
    const labelText = 'Accept Riverty | AfterPay conditions:s';
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
            <BirthDayField sectionId={paymentMethod} onBirthdateChange={handleBirthdateChange}/>
            <TermsAndConditionsCheckbox paymentMethod={paymentMethod} onCheckboxChange={handleTermsCheckboxChange}  labelText={labelText} termsUrl={termsUrl}/>
            <FinancialWarning paymentMethod={paymentMethod} />
        </div>
    );

};

export default AfterPayView;
