import React, { useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';

// Markup and class names of the WooCommerce Blocks form components, so the fields
// pick up the checkout block's own styling. The components themselves are not
// used: wc.blocksComponents is missing on the older WooCommerce versions the plugin
// supports, and its text input needs the validation store wired up.

const textInputClass = (isActive, className = '') =>
    ['wc-block-components-text-input', className, isActive && 'is-active'].filter(Boolean).join(' ');

// WooCommerce's own string, so optional fields read exactly like the checkout's
// address fields; the Checkout Block loads its translation for those.
const optionalLabel = label => sprintf(__('%s (optional)', 'woocommerce'), label);

export function TextField({
    id,
    name = id,
    label,
    required = true,
    type = 'text',
    value,
    onChange,
    className = '',
    ...inputProps
}) {
    const [isFocused, setIsFocused] = useState(false);
    const [ownValue, setOwnValue] = useState('');
    const currentValue = value !== undefined ? value : ownValue;
    const isActive = isFocused || String(currentValue ?? '') !== '';
    const shownLabel = required ? label : optionalLabel(label);

    const handleChange = e => {
        if (value === undefined) {
            setOwnValue(e.target.value);
        }
        if (onChange) {
            onChange(e);
        }
    };

    return (
        <div className={textInputClass(isActive, className)}>
            <input
                id={id}
                name={name}
                type={type}
                value={currentValue}
                onChange={handleChange}
                onFocus={() => setIsFocused(true)}
                onBlur={() => setIsFocused(false)}
                aria-label={shownLabel}
                aria-required={required}
                {...inputProps}
            />
            <label htmlFor={id}>{shownLabel}</label>
        </div>
    );
}

// Floating-label wrapper for inputs rendered by another component (the datepicker).
export function TextFieldWrapper({ id, label, isActive, children }) {
    return (
        <div className={textInputClass(isActive)}>
            {children}
            <label htmlFor={id}>{label}</label>
        </div>
    );
}

export function CheckboxField({ id, name = id, label, checked, onChange, required = false, value }) {
    return (
        <div className="wc-block-components-checkbox">
            <label htmlFor={id}>
                <input
                    id={id}
                    name={name}
                    className="wc-block-components-checkbox__input"
                    type="checkbox"
                    checked={checked}
                    value={value}
                    onChange={e => onChange(e.target.checked)}
                    aria-required={required}
                />
                <svg
                    className="wc-block-components-checkbox__mark"
                    aria-hidden="true"
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 20"
                >
                    <path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z" />
                </svg>
                <span className="wc-block-components-checkbox__label">{label}</span>
            </label>
        </div>
    );
}

export function SelectField({ id, name = id, label, options, onChange, value, required = true }) {
    return (
        <div className="wc-block-components-select-input">
            <div className="wc-blocks-components-select">
                <div className="wc-blocks-components-select__container">
                    <label htmlFor={id} className="wc-blocks-components-select__label">
                        {label}
                    </label>
                    <select
                        className="wc-blocks-components-select__select"
                        id={id}
                        name={name}
                        onChange={onChange}
                        value={value}
                        aria-required={required}
                    >
                        {options.map(option => (
                            <option key={option.key ?? option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                    <svg
                        className="wc-blocks-components-select__expand"
                        viewBox="0 0 24 24"
                        xmlns="http://www.w3.org/2000/svg"
                        width="24"
                        height="24"
                        aria-hidden="true"
                        focusable="false"
                    >
                        <path d="M17.5 11.6L12 16l-5.5-4.4.9-1.2L12 14l4.6-3.6 1 1.2z" />
                    </svg>
                </div>
            </div>
        </div>
    );
}
