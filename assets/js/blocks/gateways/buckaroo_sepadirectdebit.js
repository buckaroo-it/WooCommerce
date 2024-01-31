import React, { useState } from 'react';

const SepaDirectDebitForm = ({ onAccountName, onIbanChange, onBicChange }) => {

    return (
        <div>
            <div className="form-row form-row-wide validate-required">
                <label htmlFor="buckaroo-sepadirectdebit-accountname">
                    Bank account holder: <span className="required">*</span>
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
                    IBAN: <span className="required">*</span>
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
                    BIC:
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
            <div className="required" style={{ float: 'right' }}>
                * Required
            </div>
            <br/>
        </div>
    );
};

export default SepaDirectDebitForm;
