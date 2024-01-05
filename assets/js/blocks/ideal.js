import React from 'react';

const IdealDropdown = ({ issuers }) => {
    return (
        <select name="buckaroo-ideal-issuer" id="buckaroo-ideal-issuer">
            <option value="0">Select your bank</option>
            {issuers.map((issuer) => (
                <option key={issuer.code} value={issuer.code}>
                    {issuer.name}
                </option>
            ))}
        </select>
    );
};

export default IdealDropdown;
