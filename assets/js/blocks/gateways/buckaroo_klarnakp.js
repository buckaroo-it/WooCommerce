import React, {useContext} from 'react';
import GenderDropdown from "../partials/buckaroo_gender";
import FinancialWarning from "../partials/buckaroo_financial_warning";
import PaymentContext from '../PaymentProvider';

const KlarnaKp = ({ methodName, gateway: { genders } }) => {
    const { handleChange } = useContext(PaymentContext);

    return (
        <div id="buckaroo_klarnapay">
            <GenderDropdown paymentMethod={methodName} genders={genders} handleChange={handleChange}></GenderDropdown>
            <FinancialWarning></FinancialWarning>
        </div>
    );

};

export default KlarnaKp;
