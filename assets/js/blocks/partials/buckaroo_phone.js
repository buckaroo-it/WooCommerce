import React from 'react';
import { __ } from '@wordpress/i18n';
import { TextField } from '../components/CheckoutFields';

function PhoneDropdown({ paymentMethod, formState, handlePhoneChange }) {
    const handleChange = e => {
        const { value } = e.target;
        handlePhoneChange(value);
    };

    return (
        <TextField
            id={`${paymentMethod}-phone`}
            label={__('Phone Number:', 'wc-buckaroo-bpe-gateway')}
            type="tel"
            autoComplete="off"
            value={formState[`${paymentMethod}-phone`] || ''}
            onChange={handleChange}
        />
    );
}

export default PhoneDropdown;
