import React from 'react';

const PayByBankDropdown = () => {
    const issuers = payByBankIssuers;

    return (
        <select name="buckaroo-paybybank-issuer" id="buckaroo-paybybank-issuer">
            <option value="0">Select your bank</option>
            {Object.keys(issuers).map((issuerCode) => (
                <option key={issuerCode} value={issuerCode}>
                    {issuers[issuerCode].name}
                </option>
            ))}
        </select>
    );

};

export default PayByBankDropdown;
