import React,{useState} from 'react';
import GenderDropdown from "../partials/buckaroo_gender";
import FinancialWarning from "../partials/buckaroo_financial_warning";

const KlarnaKp = ({ config,callbacks }) => {

    const {
        genders
    } = config;

    const {
        onSelectGender
    }= callbacks;

    const paymentMethod = 'buckaroo-klarnakp';

    return (
        <div id="buckaroo_klaranakp">
            <GenderDropdown paymentMethod={paymentMethod} genders={genders} onSelectGender={onSelectGender}></GenderDropdown>
            <FinancialWarning></FinancialWarning>
        </div>
    );

};

export default KlarnaKp;
