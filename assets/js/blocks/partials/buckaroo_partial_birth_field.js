import React, { useState } from 'react';
import DatePicker from 'react-datepicker';
import 'react-datepicker/dist/react-datepicker.css';
import { __ } from '@wordpress/i18n';
import { TextFieldWrapper } from '../components/CheckoutFields';
import { matchesDayFirstDate } from '../utils/utils';

function BirthDayField({ paymentMethod, handleBirthDayChange, locale = { siteLocale: 'en-GB' } }) {
    const [birthdate, setBirthdate] = useState(null);
    const [isFocused, setIsFocused] = useState(false);
    // Text typed but not (yet) parsed as a date leaves birthdate null.
    const [typedValue, setTypedValue] = useState('');
    const isActive = isFocused || birthdate !== null || typedValue !== '';

    const localeFormatMap = {
        nl_NL: 'DD-MM-JJJJ',
        default: 'DD-MM-YYYY',
    };

    const handleDateChange = (date, event) => {
        // When none of the formats match the typed text, react-datepicker falls back
        // to `new Date(value)`, which reads 12/25/1999 month-first. Only accept a typed
        // date that matches the text day-first; otherwise keep the current selection.
        const input = event?.target;
        if (
            date &&
            event?.type === 'change' &&
            input instanceof HTMLInputElement &&
            !matchesDayFirstDate(input.value, date)
        ) {
            return;
        }

        const formattedDate = date
            ?.toLocaleDateString('en-GB', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
            })
            .replace(/\//g, '-');

        setBirthdate(date);
        handleBirthDayChange(formattedDate);
    };

    return (
        <TextFieldWrapper
            id={`${paymentMethod}-birthdate`}
            label={__('Birthdate (format DD-MM-YYYY):', 'wc-buckaroo-bpe-gateway')}
            isActive={isActive}
        >
            <DatePicker
                id={`${paymentMethod}-birthdate`}
                name={`${paymentMethod}-birthdate`}
                selected={birthdate}
                onChange={handleDateChange}
                onChangeRaw={e => {
                    // Also fires for calendar clicks, whose target is not the input.
                    if (typeof e?.target?.value === 'string') {
                        setTypedValue(e.target.value);
                    }
                }}
                onFocus={() => setIsFocused(true)}
                onBlur={() => setIsFocused(false)}
                onCalendarClose={() => setIsFocused(false)}
                // The first format is used for display; the others are accepted when typed.
                // Keep in line with matchesDayFirstDate() in utils/utils.js.
                dateFormat={['dd-MM-yyyy', 'd-M-yyyy', 'd/M/yyyy', 'd.M.yyyy']}
                autoComplete="off"
                ariaRequired="true"
                // The floating label covers the input until it is active.
                placeholderText={isActive ? localeFormatMap[locale.siteLocale] || localeFormatMap.default : ''}
                showYearDropdown
                scrollableYearDropdown
                yearDropdownItemNumber={100}
                minDate={new Date(1900, 0, 1)}
                maxDate={new Date()}
                showMonthDropdown
            />
        </TextFieldWrapper>
    );
}

export default BirthDayField;
