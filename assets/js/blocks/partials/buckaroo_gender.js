import React from 'react';

const GenderDropdown = ({ paymentMethod, genders, onSelectGender }) => {
    const translateGender = (key) => {
        const translations = {
            'male': 'He/him',
            'female': 'She/her',
            'they': 'They/them',
            'unknown': 'I prefer not to say'
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
                    Gender: <span className="required">*</span>
                </label>
                <select
                    className="buckaroo-custom-select"
                    name={`buckaroo-${paymentMethod}`}
                    id={`buckaroo-${paymentMethod}`}
                    onChange={(e) => onSelectGender(e.target.value)}
                >
                    <option>Select your Gender</option>
                    {genderOptions}
                </select>
            </div>
        </div>
    );
};

export default GenderDropdown;
