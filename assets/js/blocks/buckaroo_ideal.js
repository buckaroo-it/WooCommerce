import React from 'react';

const IdealDropdown = () => {
    const issuers = idealIssuers;

    return (
        <select name="buckaroo-ideal-issuer" id="buckaroo-ideal-issuer">
            <option value="0">Select your bank</option>
            {Object.keys(issuers).map((issuerCode) => (
                <option key={issuerCode} value={issuerCode}>
                    {issuers[issuerCode].name}
                </option>
            ))}
        </select>
    );

};

export default IdealDropdown;
