import React from 'react';

const IdealDropdown = ({ idealIssuers, onSelectIssuer }) => {
    return (
        <div className="payment_box payment_method_buckaroo_ideal">
            <div className="form-row form-row-wide">
                <select className="buckaroo-custom-select" name="buckaroo-ideal-issuer" id="buckaroo-ideal-issuer"
                        onChange={(e) => onSelectIssuer(e.target.value)}>
                    <option value="0">Select your bank</option>
                    {Object.keys(idealIssuers).map((issuerCode) => (
                        <option key={issuerCode} value={issuerCode}>
                            {idealIssuers[issuerCode].name}
                        </option>
                    ))}
                </select>
            </div>
        </div>
    );

};

export default IdealDropdown;