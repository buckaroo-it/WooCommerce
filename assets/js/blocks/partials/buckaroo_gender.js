import React from 'react';
import { __ } from '@wordpress/i18n';

function GenderDropdown({ paymentMethod, genders, handleChange }) {
  const translateGender = (key) => {
    const translations = {
      male: __('He/him', 'wc-buckaroo-bpe-gateway'),
      female: __('She/her', 'wc-buckaroo-bpe-gateway'),
      they: __('They/them', 'wc-buckaroo-bpe-gateway'),
      unknown: __('I prefer not to say', 'wc-buckaroo-bpe-gateway'),
    };

    return translations[key] ? translations[key] : capitalizeFirstLetter(key);
  };

  const capitalizeFirstLetter = (string) => string.charAt(0).toUpperCase() + string.slice(1);

  let genderOptions = '';

  genderOptions = Object.entries(genders[paymentMethod]).map(([key, value]) => (
    <option value={value}>
      {translateGender(key)}
    </option>
  ));

  return (
    <div className={`payment_box payment_method_${paymentMethod}`}>
      <div className="form-row form-row-wide">
        <label htmlFor={`${paymentMethod}-gender`}>
          {__('Gender:', 'wc-buckaroo-bpe-gateway')}
          <span className="required">*</span>
        </label>
        <select
          className="buckaroo-custom-select"
          name={`${paymentMethod}-gender`}
          id={`${paymentMethod}-gender`}
          onChange={handleChange}
        >
          <option>{__('Select your Gender', 'wc-buckaroo-bpe-gateway')}</option>
          {genderOptions}
        </select>
      </div>
    </div>
  );
}

export default GenderDropdown;
