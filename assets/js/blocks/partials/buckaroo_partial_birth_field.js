import React, { useState } from 'react';
import DatePicker from 'react-datepicker';
import 'react-datepicker/dist/react-datepicker.css';

const BirthDayField = ({ paymentMethod, onBirthdateChange }) => {
    const [birthdate, setBirthdate] = useState(null);

    const validateDate = (date) => {
        return isValidDateFormat(date);
    };

    const isValidDateFormat = (date) => {
        const dateFormatRegex = /^\d{4}-\d{2}-\d{2}$/;
        return dateFormatRegex.test(date.toISOString().slice(0, 10));
    };
    const handleDateChange = (date) => {

        const formattedDate = date.toLocaleDateString('en-GB', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        }).replace(/\//g, '-');

        setBirthdate(date);
        onBirthdateChange(formattedDate);
    };

    return (
        <div className="form-row form-row-wide validate-required buckaroo_partial_birth_field">
            <label htmlFor={`${paymentMethod}-birthdate`}>
                Birthdate (format DD-MM-YYYY):
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
};

export default BirthDayField;
