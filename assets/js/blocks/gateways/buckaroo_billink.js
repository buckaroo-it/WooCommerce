import React, { useState } from 'react';
import BirthDayField from '../partials/buckaroo_partial_birth_field'
import GenderDropdown from "../partials/buckaroo_gender";
import FinancialWarning from "../partials/buckaroo_financial_warning";
import TermsAndConditionsCheckbox from "../partials/buckaroo_terms_and_condition";

const Billink = ({ config,callbacks }) => {

    const {
        genders,
        billingData,
        b2b
    } = config;

    const {
        onBirthdateChange,
        onSelectGender,
        onCheckboxChange,
    }= callbacks;

    const paymentMethod = 'buckaroo-billink';

    return (
        <div id="buckaroo_billink_b2c">
            <GenderDropdown paymentMethod={paymentMethod} genders={genders} onSelectGender={onSelectGender}></GenderDropdown>
            <BirthDayField paymentMethod={paymentMethod} onBirthdateChange={onBirthdateChange}/>
            <TermsAndConditionsCheckbox paymentMethod={paymentMethod} onCheckboxChange={onCheckboxChange} billingData={billingData} b2b={b2b}/>
            <FinancialWarning paymentMethod={paymentMethod}></FinancialWarning>
        </div>
    );

};

export default Billink;
