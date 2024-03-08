import React, { useContext, useEffect } from 'react';
import GenderDropdown from "../partials/buckaroo_gender";
import { __ } from "@wordpress/i18n";
import PaymentContext from '../PaymentProvider';

const PayPerEmailForm = ({ methodName, gateway: { genders }, billing }) => {
    const { state, updateMultiple, handleChange } = useContext(PaymentContext);

    useEffect(() => {
        updateMultiple({
            [`${methodName}-firstname`]: billing?.first_name,
            [`${methodName}-lastname`]: billing?.last_name,
            [`${methodName}-email`]: billing?.email
        })
    }, [billing?.first_name, billing?.last_name, billing?.email])

    return (
        <div>
            <GenderDropdown paymentMethod={methodName} genders={genders}
                handleChange={handleChange} />

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
                    value={state[`${methodName}-firstname`]}
                    onChange={handleChange}
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
                    value={state[`${methodName}-lastname`]}
                    onChange={handleChange}
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
                    value={state[`${methodName}-email`]}
                    onChange={handleChange}
                />
            </div>

            <div className="required" style={{ float: 'right' }}>*
                {__('Required', 'wc-buckaroo-bpe-gateway')}
            </div>
            <br />
        </div>
    );
};

export default PayPerEmailForm;
