import React, { useState, useEffect } from 'react';
import BirthDayField from '../partials/buckaroo_partial_birth_field'
import FinancialWarning from "../partials/buckaroo_financial_warning";
const In3 = ({ config,callbacks }) => {

    const {
        billingData
    } = config;

    const {
        onBirthdateChange
    }= callbacks;

    const paymentMethod = 'buckaroo-in3';

    return (
        <div>
            {config.billingData.country === "NL" &&
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
