import React from 'react';
import { __ } from '@wordpress/i18n';
import useFormData from '../hooks/useFormData';
import CoCField from '../partials/buckaroo_coc_field';

function ZakelijkOpRekening({ onStateChange, methodName, billing }) {
    const initialState = {
        [`${methodName}-company-coc-registration`]: '',
    };

    const { handleChange } = useFormData(initialState, onStateChange);

    const hasCompany = (billing?.company || '') !== '';

    return (
        <div id="buckaroo_zakelijkoprekening_b2b">
            <p className="buckaroo-zakelijkoprekening-subtext">
                {__('Betaal later', 'wc-buckaroo-bpe-gateway')}
            </p>
            <p className="buckaroo-zakelijkoprekening-tooltip">
                {__('Voor iedereen, powered by ABN AMRO.', 'wc-buckaroo-bpe-gateway')}{' '}
                <a
                    href="https://www.abnamro.nl/nl/zakelijk/betalen/zakelijk-op-rekening.html"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    {__('lees meer', 'wc-buckaroo-bpe-gateway')}
                </a>
            </p>

            {!hasCompany && (
                <p className="form-row form-row-wide">
                    {__(
                        'Available for companies in The Netherlands. Make sure a company name is filled in your billing details.',
                        'wc-buckaroo-bpe-gateway'
                    )}
                </p>
            )}

            <CoCField methodName={methodName} handleChange={handleChange} />
        </div>
    );
}

export default ZakelijkOpRekening;
