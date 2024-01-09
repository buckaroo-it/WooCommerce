import React from 'react';

const IdealDropdown = ({paymentName}) => {
    const issuers = idealIssuers;

    return (
        <div className="payment_box payment_method_buckaroo_ideal">
            <p>{`Pay with ${paymentName}`}</p>
            <div className="form-row form-row-wide">
                <select className="buckaroo-custom-select" name="buckaroo-ideal-issuer" id="buckaroo-ideal-issuer">
                    <option value="0">Select your bank</option>
                    {Object.keys(issuers).map((issuerCode) => (
                        <option key={issuerCode} value={issuerCode}>
                            {issuers[issuerCode].name}
                        </option>
                    ))}
                </select>
            </div>
        </div>
    );

};

export default IdealDropdown;
