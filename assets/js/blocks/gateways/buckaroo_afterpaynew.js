import React from 'react';
import BirthDayField from '../partials/buckaroo_partial_birth_field'
import FinancialWarning from "../partials/buckaroo_financial_warning";
import AfterPayB2B from "../partials/buckaroo_afterpay_b2b";

const AfterPayNew = () => {
    const paymentMethod = 'buckaroo-afterpaynew';

    return (
        <div>
            <BirthDayField sectionId={paymentMethod} />
            <AfterPayB2B field={paymentMethod}></AfterPayB2B>
            <FinancialWarning paymentMethod={paymentMethod} />
        </div>
    );

};

export default AfterPayNew;
