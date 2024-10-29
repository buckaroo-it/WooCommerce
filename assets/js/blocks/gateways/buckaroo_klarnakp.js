import React from 'react';
import GenderDropdown from '../partials/buckaroo_gender';
import FinancialWarning from '../partials/buckaroo_financial_warning';

function KlarnaKp({ onStateChange, methodName, title, gateway: { genders } }) {
  const handleChange = (e) => {
    const { value } = e.target;
    onStateChange({ [`${methodName}-gender`]: value });
  };

  return (
    <div id="buckaroo_klarnapay">
      <GenderDropdown paymentMethod={methodName} genders={genders} handleChange={handleChange} />
      <FinancialWarning title={title} />
    </div>
  );
}

export default KlarnaKp;
