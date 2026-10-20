import React from 'react';
import { __ } from '@wordpress/i18n';
import { SelectField } from '../components/CheckoutFields';

function DefaultDropdown({ paymentMethodId, creditCardIssuers, handleChange }) {
    const ccOptions = [
        { value: '', label: __('Select your credit card', 'wc-buckaroo-bpe-gateway') },
        ...Object.values(creditCardIssuers).map(issuer => ({ value: issuer.servicename, label: issuer.displayname })),
    ];

    return (
        <SelectField
            id={`${paymentMethodId}-creditcard-issuer`}
            label={__('Credit Card:', 'wc-buckaroo-bpe-gateway')}
            options={ccOptions}
            onChange={handleChange}
        />
    );
}

export default DefaultDropdown;
