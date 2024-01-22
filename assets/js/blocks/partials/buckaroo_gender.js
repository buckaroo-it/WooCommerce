import React from 'react';

const GenderDropdown = ({ paymentMethod, genders, onSelectGender }) => {
    let genderOptions = ``;
    if (genders.hasOwnProperty(paymentMethod)) {
        // Render options based on the payment method
        genderOptions = Object.entries(genders[paymentMethod]).map(([key, value]) => (
            <option key={value} value={value}>
                {key}
            </option>
        ));
    } else {
        // Render default "male" and "female" options
        genderOptions = (  <>
            <option value="male">Male</option>
            <option value="female">Female</option>
        </>);
    }


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
