import React from 'react';
import BirthDayField from '../partials/buckaroo_partial_birth_field'
import FinancialWarning from "../partials/buckaroo_financial_warning";

const In3 = ({onStateChange, methodName, billing}) => {

    const handleChange = (value) => {
        onStateChange({[`${methodName}-birthdate`]: value});
    };

    return (
        <div>
            {billing.country === "NL" &&
                <BirthDayField
                    paymentMethod={methodName}
                    handleChange={handleChange}
                />
            }
            <FinancialWarning paymentMethod={methodName}></FinancialWarning>
        </div>
    );

};

export default In3;
