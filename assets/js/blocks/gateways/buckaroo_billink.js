import React from 'react';
import BirthDayField from '../partials/buckaroo_partial_birth_field'
import GenderDropdown from "../partials/buckaroo_gender";
import FinancialWarning from "../partials/buckaroo_financial_warning";
import TermsAndConditionsCheckbox from "../partials/buckaroo_terms_and_condition";
import useFormData from "../hooks/useFormData";

const Billink = ({onStateChange, methodName, gateway: {genders, b2b}, billing}) => {

    const initialState = {
        [`${methodName}-gender`]: '',
        [`${methodName}-birthdate`]: '',
        [`${methodName}-b2b`]: '',
    };

    const { handleChange, updateFormState } = useFormData(initialState, onStateChange);

    const handleBirthDayChange = (value) => {
        updateFormState(`${methodName}-birthdate`, value);
    };

    const handleTermsChange = (value) => {
        updateFormState(`${methodName}-accept`, value);
    };

    return (
        <div id="buckaroo_billink_b2c">
            <GenderDropdown paymentMethod={methodName} genders={genders} handleChange={handleChange}></GenderDropdown>
            <BirthDayField paymentMethod={methodName} handleBirthDayChange={handleBirthDayChange}/>
            <TermsAndConditionsCheckbox
                paymentMethod={methodName}
                handleTermsChange={handleTermsChange}
                billingData={billing}
                b2b={b2b}
            />
            <FinancialWarning paymentMethod={methodName}></FinancialWarning>
        </div>
    );

};

export default Billink;
