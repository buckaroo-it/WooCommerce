import React from 'react';
import BirthDayField from '../partials/buckaroo_partial_birth_field';
import FinancialWarning from '../partials/buckaroo_financial_warning';
import useFormData from '../hooks/useFormData';
import PhoneDropdown from '../partials/buckaroo_phone';

function In3({ onStateChange, methodName, title, billing }) {
  const initialState = {
    [`${methodName}-phone`]: billing?.phone || '',
    [`${methodName}-birthdate`]: '',
  };

  const { formState, updateFormState } = useFormData(initialState, onStateChange);

  const handlePhoneChange = (value) => {
    updateFormState(`${methodName}-phone`, value);
  };

  const handleBirthDayChange = (value) => {
    updateFormState(`${methodName}-birthdate`, value);
  };

  return (
    <div>
      {billing.country === 'NL'
                && <BirthDayField paymentMethod={methodName} handleBirthDayChange={handleBirthDayChange} />}
      {billing.phone === ''
                && <PhoneDropdown paymentMethod={methodName} formState={formState} handlePhoneChange={handlePhoneChange} />}
      <FinancialWarning title={title} />
    </div>
  );
}

export default In3;
