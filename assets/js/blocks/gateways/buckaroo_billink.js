import React, { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import BirthDayField from '../partials/buckaroo_partial_birth_field';
import GenderDropdown from '../partials/buckaroo_gender';
import FinancialWarning from '../partials/buckaroo_financial_warning';
import TermsAndConditionsCheckbox from '../partials/buckaroo_terms_and_condition';
import useFormData from '../hooks/useFormData';
import CoCField from '../partials/buckaroo_coc_field';

function Billink({
  onStateChange, methodName, title, gateway: { genders, b2b }, billing,
}) {
  const initialState = {
    [`${methodName}-company-coc-registration`]: '',
    [`${methodName}-VatNumber`]: '',
    [`${methodName}-gender`]: '',
    [`${methodName}-birthdate`]: '',
    [`${methodName}-b2b`]: '',
  };

  const { handleChange, updateFormState } = useFormData(initialState, onStateChange);
  const [company, setCompany] = useState(billing?.company || '');

  useEffect(() => {
    setCompany(billing?.company || '');
  }, [billing?.company]);
  const handleBirthDayChange = (value) => {
    updateFormState(`${methodName}-birthdate`, value);
  };

  const handleTermsChange = (value) => {
    updateFormState(`${methodName}-accept`, value);
  };

  return (
    <div>
      {company !== '' ? (
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
      ) : (
        <div id="buckaroo_billink_b2c">
          <GenderDropdown paymentMethod={methodName} genders={genders} handleChange={handleChange} />
          <BirthDayField paymentMethod={methodName} handleBirthDayChange={handleBirthDayChange} />
        </div>
      )}
      <TermsAndConditionsCheckbox
        paymentMethod={methodName}
        handleTermsChange={handleTermsChange}
        billingData={billing}
        b2b={b2b}
      />
      <FinancialWarning title={title} />
    </div>
  );
}

export default Billink;
