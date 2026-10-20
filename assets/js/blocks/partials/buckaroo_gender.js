import React from 'react';
import { __ } from '@wordpress/i18n';
import { SelectField } from '../components/CheckoutFields';

function GenderDropdown({ paymentMethod, genders, handleChange }) {
    const translateGender = key => {
        const translations = {
            male: __('He/him', 'wc-buckaroo-bpe-gateway'),
            female: __('She/her', 'wc-buckaroo-bpe-gateway'),
            they: __('They/them', 'wc-buckaroo-bpe-gateway'),
            unknown: __('I prefer not to say', 'wc-buckaroo-bpe-gateway'),
        };

        return translations[key] ? translations[key] : capitalizeFirstLetter(key);
    };

    const capitalizeFirstLetter = string => string.charAt(0).toUpperCase() + string.slice(1);

    const genderOptions = [
        { value: '', label: __('Select your Gender', 'wc-buckaroo-bpe-gateway') },
        ...Object.entries(genders[paymentMethod]).map(([key, value]) => ({ key, value, label: translateGender(key) })),
    ];

    return (
        <SelectField
            id={`${paymentMethod}-gender`}
            label={__('Gender:', 'wc-buckaroo-bpe-gateway')}
            options={genderOptions}
            onChange={handleChange}
        />
    );
}

export default GenderDropdown;
