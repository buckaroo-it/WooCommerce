import React from 'react';
import GenderDropdown from "../partials/buckaroo_gender";
import FinancialWarning from "../partials/buckaroo_financial_warning";

const KlarnaKp = ({onStateChange, methodName, gateway: {genders}}) => {

    const handleChange = (e) => {
        const {value} = e.target;
        onStateChange({[`${methodName}-gender`]: value});
    };

    return (
        <div id="buckaroo_klarnapay">
            <GenderDropdown paymentMethod={methodName} genders={genders} handleChange={handleChange}></GenderDropdown>
            <FinancialWarning></FinancialWarning>
        </div>
    );

};

export default KlarnaKp;
