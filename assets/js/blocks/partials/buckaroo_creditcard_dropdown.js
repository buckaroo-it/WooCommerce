import React from 'react';

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
                    Gender: <span className="required">*</span>
                </label>
                <select
                    className="buckaroo-custom-select"
                    name={`buckaroo-${paymentMethod}`}
                    id={`buckaroo-${paymentMethod}`}
                    onChange={(e) => onSelectCc(e.target.value)}
                >
                    <option>Select your credit card</option>
                    {ccOptions}
                </select>
            </div>
        </div>
    );
};

export default DefaultDropdown;
