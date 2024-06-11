import React from 'react';
import { __ } from '@wordpress/i18n';

function PhoneDropdown({ paymentMethod, formState, handlePhoneChange }) {
  const handleChange = (e) => {
    const { value } = e.target;
    handlePhoneChange(value);
  };

  return (
    <div className="form-row validate-required">
      <label htmlFor={`${paymentMethod}-phone`}>
        {__('Phone Number:', 'wc-buckaroo-bpe-gateway')}
        <span className="required">*</span>
      </label>
      <input
        id={`${paymentMethod}-phone`}
        name={`${paymentMethod}-phone`}
        className="input-text"
        type="tel"
        autoComplete="off"
        value={formState[`${paymentMethod}-phone`] || ''}
        onChange={handleChange}
      />
    </div>
  );
}

export default PhoneDropdown;
