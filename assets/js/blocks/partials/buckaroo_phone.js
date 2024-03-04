import React, { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';

const PhoneDropdown = ({ paymentMethod, billingData, handlePhoneChange }) => {
    const [phoneNumber, setPhoneNumber] = useState('');

    useEffect(() => {
        if (billingData && billingData.phone && billingData.phone !== phoneNumber) {
            setPhoneNumber(billingData.phone);
            handlePhoneChange(billingData.phone);
        }
    }, [billingData, phoneNumber, handlePhoneChange]);

    const handleChange = (e) => {
        const value = e.target.value;
        setPhoneNumber(value);
        handlePhoneChange(value);
    };

    return (
        <div className="form-row validate-required">
            <label htmlFor={`${paymentMethod}-phone`}>
                {__('Phone Number:', 'wc-buckaroo-bpe-gateway')}
                <span className="required">*</span>
            </label>
            <input
                id={`${paymentMethod}-phone`}
                name={`${paymentMethod}-phone`}
                className="input-text"
                type="tel"
                autoComplete="off"
                value={phoneNumber}
                onChange={handleChange}
            />
        </div>
    );
};

export default PhoneDropdown;
