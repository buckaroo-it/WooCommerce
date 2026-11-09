import React from 'react';
import { __ } from '@wordpress/i18n';
import GenderDropdown from '../partials/buckaroo_gender';
import useFormData from '../hooks/useFormData';
import { TextField } from '../components/CheckoutFields';

function PayPerEmailForm({ onStateChange, methodName, gateway: { genders }, billing }) {
    const initialState = {
        [`${methodName}-firstname`]: billing?.first_name || '',
        [`${methodName}-lastname`]: billing?.last_name || '',
        [`${methodName}-email`]: billing?.email || '',
        [`${methodName}-gender`]: '',
    };

    const { formState, handleChange } = useFormData(initialState, onStateChange);

    return (
        <div>
            <GenderDropdown paymentMethod={methodName} genders={genders} handleChange={handleChange} />

            <TextField
                id="buckaroo-payperemail-firstname"
                label={__('First Name:', 'wc-buckaroo-bpe-gateway')}
                autoComplete="off"
                value={formState[`${methodName}-firstname`] || ''}
                onChange={handleChange}
            />

            <TextField
                id="buckaroo-payperemail-lastname"
                label={__('Last Name:', 'wc-buckaroo-bpe-gateway')}
                autoComplete="off"
                value={formState[`${methodName}-lastname`] || ''}
                onChange={handleChange}
            />

            <TextField
                id="buckaroo-payperemail-email"
                label={__('Email:', 'wc-buckaroo-bpe-gateway')}
                type="email"
                autoComplete="off"
                value={formState[`${methodName}-email`] || ''}
                onChange={handleChange}
            />
        </div>
    );
}

export default PayPerEmailForm;
