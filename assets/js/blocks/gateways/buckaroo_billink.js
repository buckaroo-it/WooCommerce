import React, { useState, useEffect } from 'react';
import BirthDayField from '../partials/buckaroo_partial_birth_field'
import GenderDropdown from "../partials/buckaroo_gender";
import FinancialWarning from "../partials/buckaroo_financial_warning";
import TermsAndConditionsCheckbox from "../partials/buckaroo_terms_and_condition";
const Billink = ({genders, onBirthdateChange, onSelectGender,onCheckboxChange}) => {
    const paymentMethod = 'buckaroo-billink';

    const [gender, setGender] = useState(null);
    const [isTermsAccepted, setIsTermsAccepted] = useState(false);
    const labelText = 'Accept terms of use';
    const termsUrl = 'tests'
    const handleTermsCheckboxChange = (isChecked) => {
        setIsTermsAccepted(isChecked);
        onCheckboxChange(isChecked)
    };

    const handleBirthdateChange = (date) => {
        onBirthdateChange(date);
    };

    const handleSelectGender = (selectedGender) => {
        setGender(selectedGender);
        onSelectGender(selectedGender)
    };

    return (
        <fieldset id="buckaroo_billink_b2c">
            <GenderDropdown paymentMethod={paymentMethod} genders={genders} onSelectGender={handleSelectGender}></GenderDropdown>
            <BirthDayField sectionId={paymentMethod} onBirthdateChange={handleBirthdateChange}/>
            <TermsAndConditionsCheckbox paymentMethod={paymentMethod} onCheckboxChange={handleTermsCheckboxChange}  labelText={labelText} termsUrl={termsUrl}/>
            <FinancialWarning paymentMethod={paymentMethod}></FinancialWarning>
        </fieldset>
    );

};

export default Billink;
