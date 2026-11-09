import React from 'react';
import { __ } from '@wordpress/i18n';
import useFormData from '../hooks/useFormData';
import CoCField from '../partials/buckaroo_coc_field';
import PhoneDropdown from '../partials/buckaroo_phone';
import { TextField } from '../components/CheckoutFields';

function ZakelijkOpRekening({ onStateChange, methodName, billing }) {
    const initialState = {
        [`${methodName}-company`]: billing?.company || '',
        [`${methodName}-company-coc-registration`]: '',
        [`${methodName}-phone`]: billing?.phone || '',
    };

    const { handleChange, formState, updateFormState } = useFormData(initialState, onStateChange);

    const hasCompany = (billing?.company || '') !== '';
    const hasPhone = (billing?.phone || '').trim() !== '';

    const handlePhoneChange = value => {
        updateFormState(`${methodName}-phone`, value);
    };

    return (
        <div id="buckaroo_zakelijkoprekening_b2b">
            <p className="buckaroo-zakelijkoprekening-subtext">{__('Betaal later', 'wc-buckaroo-bpe-gateway')}</p>
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
                <TextField
                    id={`${methodName}-company`}
                    label={__('Company name:', 'wc-buckaroo-bpe-gateway')}
                    maxLength="250"
                    autoComplete="organization"
                    onChange={handleChange}
                />
            )}

            {!hasPhone && (
                <PhoneDropdown paymentMethod={methodName} formState={formState} handlePhoneChange={handlePhoneChange} />
            )}

            <CoCField methodName={methodName} handleChange={handleChange} />
        </div>
    );
}

export default ZakelijkOpRekening;
