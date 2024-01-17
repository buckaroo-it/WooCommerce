import React, { useState, useEffect } from 'react';
import BirthDayField from '../partials/buckaroo_partial_birth_field'
import FinancialWarning from "../partials/buckaroo_financial_warning";
const In3 = ({onBirthdateChange , billingData}) => {
    const paymentMethod = 'buckaroo-in3';

    const handleBirthdateChange = (date) => {
        onBirthdateChange(date);
    };


    return (
        <fieldset>
            {billingData.country === "NL" &&
                <BirthDayField
                    paymentMethod={paymentMethod}
                    onBirthdateChange={handleBirthdateChange}
                />
            }
            <FinancialWarning paymentMethod={paymentMethod}></FinancialWarning>
        </fieldset>
    );

};

export default In3;
