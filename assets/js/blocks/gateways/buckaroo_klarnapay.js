import React from 'react';
import GenderDropdown from '../partials/buckaroo_gender';
import FinancialWarning from '../partials/buckaroo_financial_warning';

function KlarnaPay({ onStateChange, methodName, title, gateway: { genders, financialWarning } }) {
    const handleChange = e => {
        const { value } = e.target;
        onStateChange({ [`${methodName}-gender`]: value });
    };

    return (
        <div id="buckaroo_klarnapay">
            <GenderDropdown paymentMethod={methodName} genders={genders} handleChange={handleChange} />
            {financialWarning === 'enable' && <FinancialWarning title={title} />}
        </div>
    );
}

export default KlarnaPay;
