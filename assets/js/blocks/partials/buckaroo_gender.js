import React from 'react';

const GenderDropdown = ({ paymentMethod, genders, onSelectGender }) => {

    return (
        <div className={`payment_box payment_method_${paymentMethod}`}>
            <div className="form-row form-row-wide">
                <label htmlFor="buckaroo-billink-gender">
                    Gender: <span className="required">*</span>
                </label>
                <select
                    className="buckaroo-custom-select"
                    name={`buckaroo-${paymentMethod}`}
                    id={`buckaroo-${paymentMethod}`}
                    onChange={(e) => onSelectGender(e.target.value)}
                >
                    <option>Select your Gender</option>
                    {Object.entries(genders[paymentMethod]).map(([key, value]) => (
                        <option value={value}>
                            {key}
                        </option>
                    ))}
                </select>
            </div>
        </div>
    );
};

export default GenderDropdown;
