import React, { useState, useEffect } from 'react';
import BirthDayField from '../partials/buckaroo_partial_birth_field'
const Billink = ({onBirthdateChange}) => {
    const paymentMethod = 'buckaroo-billink';

    const handleBirthdateChange = (date) => {
        onBirthdateChange(date);
    };

    return (
        <div>
            <BirthDayField sectionId={paymentMethod} onBirthdateChange={handleBirthdateChange} />

        </div>
    );

};

export default Billink;
