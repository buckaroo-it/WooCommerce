import React,{useState} from 'react';
import GenderDropdown from "../partials/buckaroo_gender";
import FinancialWarning from "../partials/buckaroo_financial_warning";

const KlarnaPay = ({ config,callbacks }) => {

    const {
        genders
    } = config;

    const {
        onSelectGender
    }= callbacks;

    const paymentMethod = 'buckaroo-klarnapay';

    return (
        <div id="buckaroo_klarnapay">
            <GenderDropdown paymentMethod={paymentMethod} genders={genders} onSelectGender={onSelectGender}></GenderDropdown>
            <FinancialWarning></FinancialWarning>
        </div>
    );

};

export default KlarnaPay;
