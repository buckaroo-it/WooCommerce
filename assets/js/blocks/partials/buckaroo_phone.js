import React, {useState, useEffect} from 'react';
import {__} from "@wordpress/i18n";

const PhoneDropdown = ({paymentMethod, billingData, onPhoneNumberChange}) => {
    let phoneNumberLabel = __('Phone Number:', 'wc-buckaroo-bpe-gateway');
    const [phoneNumber, setPhoneNumber] = useState('');
    useEffect(() => {
        if (billingData) {
            setPhoneNumber(billingData.phone || '');

            onPhoneNumberChange(billingData.phone || '');
        }
    }, [billingData]);
    return (
        <div className="form-row validate-required">
            <label htmlFor={`${paymentMethod}-phone`}>
                {phoneNumberLabel}
                <span className="required">*</span>
            </label>
            <input
                id={`buckaroo-${paymentMethod}`}
                name={`buckaroo-${paymentMethod}`}
                className="input-text"
                type="tel"
                autoComplete="off"
                value={phoneNumber}
                onChange={(e) => {
                    onPhoneNumberChange(e.target.value);
                }}
            />
        </div>
    );
};

export default PhoneDropdown;
