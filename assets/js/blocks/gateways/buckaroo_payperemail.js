import React, { useState, useEffect } from 'react';
import GenderDropdown from "../partials/buckaroo_gender";

const PayPerEmailForm = ({genders, onSelectGender, onFirstNameChange, onLastNameChange, onEmailChange, billingData}) => {
    const [gender, setGender] = useState(null);
    const [firstName, setFirstName] = useState('');
    const [lastName, setLastName] = useState('');
    const [email, setEmail] = useState('');
    const paymentMethod = 'buckaroo-payperemail';

    useEffect(() => {
        if(billingData) {
            setFirstName(billingData.first_name || '');
            setLastName(billingData.last_name || '');
            setEmail(billingData.email || '');

            onFirstNameChange(billingData.first_name || '');
            onLastNameChange(billingData.last_name || '');
            onEmailChange(billingData.email || '');
        }
    }, [billingData]);

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
                    value={firstName}
                    onChange={(e) => {
                        setFirstName(e.target.value);
                        onFirstNameChange(e.target.value);
                    }}
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
                    value={lastName}
                    onChange={(e) => {
                        setLastName(e.target.value);
                        onLastNameChange(e.target.value);
                    }}
                />
            </p>

            <p className="form-row validate-required">
                <label htmlFor="buckaroo-payperemail-email">
                    Email: <span className="required">*</span>
                </label>
                <input
                    id="buckaroo-payperemail-email"
                    name="buckaroo-payperemail-email"
                    className="input-text"
                    type="email"
                    autoComplete="off"
                    value={email}
                    onChange={(e) => {
                        setEmail(e.target.value);
                        onEmailChange(e.target.value);
                    }}
                />
            </p>

            <p className="required" style={{ float: 'right' }}>
                * Required
            </p>
        </div>
    );
};

export default PayPerEmailForm;
