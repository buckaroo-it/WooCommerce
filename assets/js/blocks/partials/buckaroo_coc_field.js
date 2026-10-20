import React from 'react';
import { __ } from '@wordpress/i18n';
import { TextField } from '../components/CheckoutFields';

function CoCField({ methodName, handleChange }) {
    return (
        <TextField
            id={`${methodName}-company-coc-registration`}
            label={__('CoC-number:', 'wc-buckaroo-bpe-gateway')}
            maxLength="250"
            autoComplete="off"
            onChange={handleChange}
        />
    );
}

export default CoCField;
