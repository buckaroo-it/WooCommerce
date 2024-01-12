import React, { useState } from 'react';

const TermsAndConditionsCheckbox = ({ paymentMethod, onCheckboxChange,labelText,termsUrl}) => {
    const [isChecked, setIsChecked] = useState(false);

    const handleCheckboxChange = () => {
        setIsChecked(!isChecked);
        onCheckboxChange(!isChecked ? 'on' : 'off');
    };


    return (
        <div>
            <a href={`${termsUrl}`} target="_blank">{labelText}</a>
            <span className="required">*</span>
            <input
                id={`${paymentMethod}-accept`}
                name={`${paymentMethod}-accept`}
                type="checkbox"
                value="ON"
                checked={isChecked}
                onChange={handleCheckboxChange}
            />
            <p className="required" style={{ float: 'right' }}>*
                Required
            </p>
        </div>
    );
};

export default TermsAndConditionsCheckbox;
