import React from 'react';
import GenderDropdown from '../partials/buckaroo_gender';

function KlarnaPay({ onStateChange, methodName, gateway: { genders } }) {
    const handleChange = e => {
        const { value } = e.target;
        onStateChange({ [`${methodName}-gender`]: value });
    };

    return (
        <div id="buckaroo_klarnapay">
            <GenderDropdown paymentMethod={methodName} genders={genders} handleChange={handleChange} />
        </div>
    );
}

export default KlarnaPay;
