import React, { useState, useEffect } from 'react';
import BirthDayField from '../partials/buckaroo_partial_birth_field'
import FinancialWarning from "../partials/buckaroo_financial_warning";
const In3 = ({onBirthdateChange , billingData}) => {
    const paymentMethod = 'buckaroo-in3';

    return (
        <div>
            {billingData.country === "NL" &&
                <BirthDayField
                    paymentMethod={paymentMethod}
                    onBirthdateChange={onBirthdateChange}
                />
            }
            <FinancialWarning paymentMethod={paymentMethod}></FinancialWarning>
        </div>
    );

};

export default In3;
