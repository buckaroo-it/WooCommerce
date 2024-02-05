import React, { useState, useEffect } from 'react';
import GenderDropdown from "../partials/buckaroo_gender";
import {__} from "@wordpress/i18n";

const PayPerEmailForm = ({ config,callbacks }) => {

    const {
        genders,
        billingData,
    } = config;

    const {
        onSelectGender,
        onFirstNameChange,
        onLastNameChange,
        onEmailChange
    }= callbacks;

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
            <GenderDropdown paymentMethod={paymentMethod} genders={genders}
                            onSelectGender={handleSelectGender}></GenderDropdown>

            <div className="form-row validate-required">
                <label htmlFor="buckaroo-payperemail-firstname">
                    {__('First Name:', 'wc-buckaroo-bpe-gateway')}
                    <span className="required">*</span>
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
            </div>

            <div className="form-row validate-required">
                <label htmlFor="buckaroo-payperemail-lastname">
                    {__('Last Name:', 'wc-buckaroo-bpe-gateway')}
                    <span className="required">*</span>
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
            </div>

            <div className="form-row validate-required">
                <label htmlFor="buckaroo-payperemail-email">
                    {__('Email:', 'wc-buckaroo-bpe-gateway')}
                    <span className="required">*</span>
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
            </div>

            <div className="required" style={{float: 'right'}}>*
                {__('Required', 'wc-buckaroo-bpe-gateway')}
            </div>
            <br/>
        </div>
    );
};

export default PayPerEmailForm;
