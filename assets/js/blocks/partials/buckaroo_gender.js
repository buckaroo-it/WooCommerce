import React from 'react';
import {__} from "@wordpress/i18n";

const GenderDropdown = ({ paymentMethod, genders, onSelectGender }) => {

    const translateGender = (key) => {
        const translations = {
            'male': __('He/him', 'wc-buckaroo-bpe-gateway'),
            'female': __('She/her', 'wc-buckaroo-bpe-gateway'),
            'they': __('They/them', 'wc-buckaroo-bpe-gateway'),
            'unknown': __('I prefer not to say', 'wc-buckaroo-bpe-gateway')
        };
        return translations[key] || key;
    };

    let genderOptions = ``;

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
                    name={`buckaroo-${paymentMethod}`}
                    id={`buckaroo-${paymentMethod}`}
                    onChange={(e) => onSelectGender(e.target.value)}
                >
                    <option>{__('Select your Gender', 'wc-buckaroo-bpe-gateway')}</option>
                    {genderOptions}
                </select>
            </div>
        </div>
    );
};

export default GenderDropdown;
