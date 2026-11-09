import React from 'react';
import { __ } from '@wordpress/i18n';
import { TextField } from '../components/CheckoutFields';

function AfterPayB2B({ handleChange }) {
    return (
        <div id="showB2BBuckaroo">
            <p>{__('Fill required fields if bill in on the company:', 'wc-buckaroo-bpe-gateway')}</p>
            <TextField
                id="buckaroo-afterpay-company-coc-registration"
                label={__('COC (KvK) number:', 'wc-buckaroo-bpe-gateway')}
                maxLength="250"
                autoComplete="off"
                onChange={handleChange}
            />
            <TextField
                id="buckaroo-afterpay-company-name"
                label={__('Name of the organization:', 'wc-buckaroo-bpe-gateway')}
                maxLength="250"
                autoComplete="off"
                onChange={handleChange}
            />
        </div>
    );
}

export default AfterPayB2B;
