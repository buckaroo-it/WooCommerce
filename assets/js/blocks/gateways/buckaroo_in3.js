import React from 'react';
import BirthDayField from '../partials/buckaroo_partial_birth_field'
import FinancialWarning from "../partials/buckaroo_financial_warning";
import useFormData from "../hooks/useFormData";
import PhoneDropdown from "../partials/buckaroo_phone";

const In3 = ({onStateChange, methodName, billing}) => {

    const initialState = {
        [`${methodName}-birthdate`]: '',
        [`${methodName}-phone`]: billing.phone || '',
    };

    const [formState, handleChange, updateFormState] = useFormData(initialState, onStateChange);

    const handlePhoneChange = (value) => {
        updateFormState(`${methodName}-phone`, value);
    };


    const handleBirthDayChange = (value) => {
        updateFormState(`${methodName}-birthdate`, value);
    };

    return (
        <div>
            {billing.country === "NL" &&
                <BirthDayField paymentMethod={methodName} handleBirthDayChange={handleBirthDayChange}/>
            }
            {billing.phone === "" &&
                <PhoneDropdown paymentMethod={methodName} billingData={billing} handlePhoneChange={handlePhoneChange}/>
            }
            <FinancialWarning paymentMethod={methodName}></FinancialWarning>
        </div>
    );

};

export default In3;
