import React from 'react';
import {__} from "@wordpress/i18n";

const DefaultDropdown = ({ paymentMethod, creditCardIssuers, onSelectCc }) => {

    let ccOptions = ``;
    ccOptions = Object.entries(creditCardIssuers).map(([key, value]) => (

        <option key={value.servicename} value={value.servicename}>
            {value.displayname}
        </option>
    ));


    return (
        <div className={`payment_box payment_method_${paymentMethod}`}>
            <div className="form-row form-row-wide">
                <label htmlFor="buckaroo-billink-creditcard">
                    {__('Credit Card:', 'wc-buckaroo-bpe-gateway')}
                    <span className="required">*</span>
                </label>
                <select
                    className="buckaroo-custom-select"
                    name={`buckaroo-${paymentMethod}`}
                    id={`buckaroo-${paymentMethod}`}
                    onChange={(e) => onSelectCc(e.target.value)}
                >
                    <option>{__('Select your credit card', 'wc-buckaroo-bpe-gateway')}</option>
                    {ccOptions}
                </select>
            </div>
        </div>
    );
};

export default DefaultDropdown;
