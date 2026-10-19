import React from 'react';
import { __ } from '@wordpress/i18n';
import useFormData from '../hooks/useFormData';
import { TextField } from '../components/CheckoutFields';

function SepaDirectDebitForm({ onStateChange, methodName, billing }) {
    const initialState = {
        [`${methodName}-accountname`]: `${billing.first_name} ${billing.last_name}`,
        [`${methodName}-iban`]: '',
        [`${methodName}-bic`]: '',
    };

    const { formState, handleChange } = useFormData(initialState, onStateChange);

    return (
        <div>
            <TextField
                id="buckaroo-sepadirectdebit-accountname"
                label={__('Bank account holder:', 'wc-buckaroo-bpe-gateway')}
                maxLength="250"
                autoComplete="off"
                value={formState[`${methodName}-accountname`]}
                onChange={handleChange}
            />
            <TextField
                id="buckaroo-sepadirectdebit-iban"
                label={__('IBAN:', 'wc-buckaroo-bpe-gateway')}
                maxLength="25"
                autoComplete="off"
                value={formState[`${methodName}-iban`]}
                onChange={handleChange}
            />
            <TextField
                id="buckaroo-sepadirectdebit-bic"
                label={__('BIC:', 'wc-buckaroo-bpe-gateway')}
                required={false}
                maxLength="11"
                autoComplete="off"
                value={formState[`${methodName}-bic`]}
                onChange={handleChange}
            />
        </div>
    );
}

export default SepaDirectDebitForm;
