import React, { useState, useEffect } from 'react';
import BirthDayField from '../partials/buckaroo_partial_birth_field'
import FinancialWarning from "../partials/buckaroo_financial_warning";
const In3 = ({billingCountry, onBirthdateChange}) => {
    const paymentMethod = 'buckaroo-in3';

    const handleBirthdateChange = (date) => {
        onBirthdateChange(date);
    };
    return (
        <fieldset>
            {billingCountry === "NL" &&
                <BirthDayField
                    sectionId={paymentMethod}
                    onBirthdateChange={handleBirthdateChange}
                />
            }
            <FinancialWarning paymentMethod={paymentMethod}></FinancialWarning>
        </fieldset>
    );

};

export default In3;
