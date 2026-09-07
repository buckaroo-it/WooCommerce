import React, { useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import useFormData from '../hooks/useFormData';
import CoCField from '../partials/buckaroo_coc_field';

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
            <p className="form-row form-row-wide validate-required">
                <label htmlFor={`${methodName}-VatNumber`}>
                    {__('VAT-number:', 'wc-buckaroo-bpe-gateway')}
                    <span className="required">*</span>
                </label>
                <input
                    id={`${methodName}-VatNumber`}
                    name={`${methodName}-VatNumber`}
                    className="input-text"
                    type="text"
                    maxLength="250"
                    autoComplete="off"
                    onChange={handleChange}
                />
            </p>
        </div>
    );
}

export default Billink;
