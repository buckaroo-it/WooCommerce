import React, { useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import useFormData from '../hooks/useFormData';
import CoCField from '../partials/buckaroo_coc_field';
import { TextField } from '../components/CheckoutFields';

function Billink({ onStateChange, methodName, billing }) {
    const initialState = {
        [`${methodName}-company-coc-registration`]: '',
        [`${methodName}-VatNumber`]: '',
        [`${methodName}-b2b`]: '',
    };

    const { handleChange } = useFormData(initialState, onStateChange);
    const [company, setCompany] = useState(billing?.company || '');

    useEffect(() => {
        setCompany(billing?.company || '');
    }, [billing?.company]);

    // B2C needs no fields of its own: Billink One collects the date of birth
    // and shows its terms on its own page.
    if (company === '') {
        return null;
    }

    return (
        <div id="buckaroo_billink_b2b">
            <CoCField methodName={methodName} handleChange={handleChange} />
            <TextField
                id={`${methodName}-VatNumber`}
                label={__('VAT-number:', 'wc-buckaroo-bpe-gateway')}
                maxLength="250"
                autoComplete="off"
                onChange={handleChange}
            />
        </div>
    );
}

export default Billink;
