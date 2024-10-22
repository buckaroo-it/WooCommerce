import React, { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import BirthDayField from '../partials/buckaroo_partial_birth_field';
import FinancialWarning from '../partials/buckaroo_financial_warning';
import TermsAndConditionsCheckbox from '../partials/buckaroo_terms_and_condition';
import PhoneDropdown from '../partials/buckaroo_phone';
import useFormData from '../hooks/useFormData';
import CoCField from '../partials/buckaroo_coc_field';

function AfterPayNew({
  onStateChange, methodName, title, gateway: { customer_type, b2b }, billing,
}) {
  const initialState = {
    [`${methodName}-phone`]: billing?.phone || '',
    [`${methodName}-birthdate`]: '',
    [`${methodName}-company-coc-registration`]: '',
    [`${methodName}-accept`]: '',
  };

  const { formState, handleChange, updateFormState } = useFormData(initialState, onStateChange);

  const [company, setCompany] = useState(billing?.company || '');

  useEffect(() => {
    setCompany(billing?.company || '');
  }, [billing?.company]);

  const handleTermsChange = (value) => {
    updateFormState(`${methodName}-accept`, value);
  };

  const handleBirthDayChange = (value) => {
    updateFormState(`${methodName}-birthdate`, value);
  };

  const handlePhoneChange = (value) => {
    updateFormState(`${methodName}-phone`, value);
  };

  return (
    <div>
      <PhoneDropdown paymentMethod={methodName} formState={formState} handlePhoneChange={handlePhoneChange} />

      {(['BE', 'NL', 'DE'].includes(billing.country)) && (
      <div>
        <BirthDayField paymentMethod={methodName} handleBirthDayChange={handleBirthDayChange} />
      </div>
      )}

      {company !== '' && billing.country === 'NL' && customer_type !== 'b2c' && (
      <CoCField methodName={methodName} handleChange={handleChange} />
      )}

      {billing.country === 'FI' && (
      <p className="form-row form-row-wide validate-required">
        <label htmlFor="buckaroo-afterpaynew-identification-number">
          {__('Identification Number:', 'wc-buckaroo-bpe-gateway')}
          <span className="required">*</span>
        </label>
        <input
          id="buckaroo-afterpaynew-identification-number"
          name="buckaroo-afterpaynew-identification-number"
          className="input-text"
          type="text"
          maxLength="250"
          autoComplete="off"
          onChange={handleChange}
        />
      </p>
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

export default AfterPayNew;
