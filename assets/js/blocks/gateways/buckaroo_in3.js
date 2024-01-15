import React, { useState, useEffect } from 'react';
import BirthDayField from '../partials/buckaroo_partial_birth_field'
import FinancialWarning from "../partials/buckaroo_financial_warning";
const In3 = ({ onBirthdateChange}) => {
    const paymentMethod = 'buckaroo-in3';
    // console.log('billingCountry',billingCountry)

    const [selectedCountry, setSelectedCountry] = useState('');

    useEffect(() => {
        const countrySelect = document.getElementById('components-form-token-input-0');
        setSelectedCountry(countrySelect.value);
        const handleCountryChange = () => {
            setSelectedCountry(countrySelect.value);
        };
        countrySelect.addEventListener('change', handleCountryChange);

        return () => {
            countrySelect.removeEventListener('change', handleCountryChange);
        };
    }, []);
    const handleBirthdateChange = (date) => {
        onBirthdateChange(date);
    };


    return (
        <fieldset>
            {selectedCountry === "Netherlands" &&
                <BirthDayField
                    paymentMethod={paymentMethod}
                    onBirthdateChange={handleBirthdateChange}
                />
            }
            <FinancialWarning paymentMethod={paymentMethod}></FinancialWarning>
        </fieldset>
    );

};

export default In3;
