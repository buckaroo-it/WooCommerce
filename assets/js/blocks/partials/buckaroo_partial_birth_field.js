import React, { useState } from 'react';
import DatePicker from 'react-datepicker';
import 'react-datepicker/dist/react-datepicker.css';
import { __ } from '@wordpress/i18n';

function BirthDayField({ paymentMethod, handleBirthDayChange }) {
  const [birthdate, setBirthdate] = useState(null);

  const handleDateChange = (date) => {
    const formattedDate = date.toLocaleDateString('en-GB', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
    }).replace(/\//g, '-');

    setBirthdate(date);
    handleBirthDayChange(formattedDate);
  };

  return (
    <div className="form-row form-row-wide validate-required">
      <label htmlFor={`${paymentMethod}-birthdate`}>
        {__('Birthdate (format DD-MM-YYYY):', 'wc-buckaroo-bpe-gateway')}
        <span className="required">*</span>
      </label>

      <DatePicker
        id={`${paymentMethod}-birthdate`}
        name={`${paymentMethod}-birthdate`}
        selected={birthdate}
        onChange={handleDateChange}
        dateFormat="dd-MM-yyyy"
        className="input-text"
        autoComplete="off"
        placeholderText="DD-MM-YYYY"
        showYearDropdown
        scrollableYearDropdown
        yearDropdownItemNumber={100}
        minDate={new Date(1900, 0, 1)}
        maxDate={new Date()}
        showMonthDropdown
      />
    </div>
  );
}

export default BirthDayField;
