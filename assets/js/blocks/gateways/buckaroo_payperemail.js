import React, { useState, useEffect } from 'react';
import GenderDropdown from "../partials/buckaroo_gender";

const PayPerEmailForm = ({genders, onSelectGender, onFirstNameChange, onLastNameChange, onEmailChange }) => {
    const [gender, setGender] = useState(null);
    const paymentMethod = 'buckaroo-payperemail';

    const handleSelectGender = (selectedGender) => {
        setGender(selectedGender);
        onSelectGender(selectedGender)
    };

    return (
        <div>
            <GenderDropdown paymentMethod={paymentMethod} genders={genders} onSelectGender={handleSelectGender}></GenderDropdown>

            <p className="form-row validate-required">
                <label htmlFor="buckaroo-payperemail-firstname">
                    First Name: <span className="required">*</span>
                </label>
                <input
                    id="buckaroo-payperemail-firstname"
                    name="buckaroo-payperemail-firstname"
                    className="input-text"
                    type="text"
                    autoComplete="off"
                    onChange={(e) => onFirstNameChange(e.target.value)}
                />
            </p>

            <p className="form-row validate-required">
                <label htmlFor="buckaroo-payperemail-lastname">
                    Last Name: <span className="required">*</span>
                </label>
                <input
                    id="buckaroo-payperemail-lastname"
                    name="buckaroo-payperemail-lastname"
                    className="input-text"
                    type="text"
                    autoComplete="off"
                    onChange={(e) => onLastNameChange(e.target.value)}
                />
            </p>

            <p className="form-row validate-required">
                <label htmlFor="buckaroo-payperemail-email">
                    Email: <span className="required">*</span>
                </label>
                <input
                    id="buckaroo-payperemail-email"
                    name="buckaroo-payperemail-email"
                    type="email"
                    autoComplete="off"
                    onChange={(e) => onEmailChange(e.target.value)}
                />
            </p>

            <p className="required" style={{ float: 'right' }}>
                * Required
            </p>
        </div>
    );
};

export default PayPerEmailForm;
