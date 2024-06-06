import React from 'react';
import { __ } from '@wordpress/i18n';

const CoCField = ({ methodName, handleChange }) => (
    <p className="form-row form-row-wide validate-required">
        <label htmlFor={`${methodName}-company-coc-registration`}>
            {__('CoC-number:', 'wc-buckaroo-bpe-gateway')}
            <span className="required">*</span>
        </label>
        <input
            id={`${methodName}-company-coc-registration`}
            name={`${methodName}-company-coc-registration`}
            className="input-text"
            type="text"
            maxLength="250"
            autoComplete="off"
            onChange={handleChange}
        />
    </p>
);

export default CoCField;
