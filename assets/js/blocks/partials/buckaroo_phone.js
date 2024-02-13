import React, {useState, useEffect} from 'react';
import {__} from "@wordpress/i18n";

const PhoneDropdown = ({paymentMethod, billingData, handlePhoneChange}) => {
    const [phoneNumber, setPhoneNumber] = useState('');
    useEffect(() => {
        if (billingData) {
            setPhoneNumber(billingData.phone || '');

            handlePhoneChange(billingData.phone || '');
        }
    }, [billingData]);
    return (
        <div className="form-row validate-required">
            <label htmlFor={`${paymentMethod}-phone`}>
                {__('Phone Number:', 'wc-buckaroo-bpe-gateway')}
                <span className="required">*</span>
            </label>
            <input
                id={`buckaroo-${paymentMethod}`}
                name={`buckaroo-${paymentMethod}`}
                className="input-text"
                type="tel"
                autoComplete="off"
                value={phoneNumber}
                onChange={handlePhoneChange}
            />
        </div>
    );
};

export default PhoneDropdown;
