import React from 'react';
import BirthDayField from '../partials/buckaroo_partial_birth_field'
import FinancialWarning from "../partials/buckaroo_financial_warning";
import useFormData from "../hooks/useFormData";

const In3 = ({onStateChange, methodName, billing: { country }}) => {

    const initialState = {
        [`${methodName}-birthdate`]: '',
    };

    const [formState, handleChange, updateFormState] = useFormData(initialState, onStateChange);

    const handleBirthDayChange = (value) => {
        updateFormState(`${methodName}-birthdate`, value);
    };

    return (
        <div>
            {country === "NL" &&
                <BirthDayField paymentMethod={methodName} handleBirthDayChange={handleBirthDayChange}/>
            }
            <FinancialWarning paymentMethod={methodName}></FinancialWarning>
        </div>
    );

};

export default In3;
