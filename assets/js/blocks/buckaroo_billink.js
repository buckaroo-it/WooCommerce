import React from 'react';
import BirthDayField from './partials/buckaroo_partial_birth_field'
const Billink = () => {
    const paymentMethod = 'buckaroo-billink';

    return (
        <div>
            <BirthDayField sectionId={paymentMethod}></BirthDayField>
        </div>
    );

};

export default Billink;
