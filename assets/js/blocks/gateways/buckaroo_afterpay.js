import React from 'react';
import BirthDayField from '../partials/buckaroo_partial_birth_field'
import FinancialWarning from "../partials/buckaroo_financial_warning";
import AfterPayTos from "../partials/buckaroo_afterpay_tos";

const AfterPayView = () => {
    const paymentMethod = 'buckaroo-afterpay';

    return (
        <div>
            <BirthDayField sectionId={paymentMethod} />
            <AfterPayTos field={paymentMethod}></AfterPayTos>
            <FinancialWarning paymentMethod={paymentMethod} />
        </div>
    );

};

export default AfterPayView;
