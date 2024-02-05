import React, { useState } from 'react';
import {__} from "@wordpress/i18n";

const SepaDirectDebitForm = ({ onAccountName, onIbanChange, onBicChange }) => {

    return (
        <div>
            <div className="form-row form-row-wide validate-required">
                <label htmlFor="buckaroo-sepadirectdebit-accountname">
                    <span className="required">*</span>
                    {__('Bank account holder:', 'wc-buckaroo-bpe-gateway')}
                </label>
                <input
                    id="buckaroo-sepadirectdebit-accountname"
                    name="buckaroo-sepadirectdebit-accountname"
                    className="input-text"
                    type="text"
                    maxLength="250"
                    autoComplete="off"
                    onChange={(e) => onAccountName(e.target.value)}
                />
            </div>
            <div className="form-row form-row-wide validate-required">
                <label htmlFor="buckaroo-sepadirectdebit-iban">
                    {__('IBAN:', 'wc-buckaroo-bpe-gateway')}
                    <span className="required">*</span>
                </label>
                <input
                    id="buckaroo-sepadirectdebit-iban"
                    name="buckaroo-sepadirectdebit-iban"
                    className="input-text"
                    type="text"
                    maxLength="25"
                    autoComplete="off"
                    onChange={(e) => onIbanChange(e.target.value)}
                />
            </div>
            <div className="form-row form-row-wide">
                <label htmlFor="buckaroo-sepadirectdebit-bic">
                    {__('BIC:', 'wc-buckaroo-bpe-gateway')}
                </label>
                <input
                    id="buckaroo-sepadirectdebit-bic"
                    name="buckaroo-sepadirectdebit-bic"
                    className="input-text"
                    type="text"
                    maxLength="11"
                    autoComplete="off"
                    onChange={(e) => onBicChange(e.target.value)}
                />
            </div>
            <div className="required" style={{float: 'right'}}>*
                {__('Required', 'wc-buckaroo-bpe-gateway')}
            </div>
            <br/>
        </div>
    );
};

export default SepaDirectDebitForm;
