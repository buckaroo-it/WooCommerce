import React, { useState } from 'react';

const PayByBankDropdown = ({ payByBankIssuers, buckarooImagesUrl, payByBankSelectedIssuer, displayMode }) => {
    const [selectedIssuer, setSelectedIssuer] = useState(payByBankSelectedIssuer);
    const [showAllBanks, setShowAllBanks] = useState(false);

    // Toggle the view of banks
    const toggleBankView = () => {
        setShowAllBanks(!showAllBanks);
    };

    // Handle issuer selection
    const handleSelectIssuer = (issuerCode) => {
        setSelectedIssuer(issuerCode);
        onSelectIssuer(issuerCode);
    };

    return (
        <div className="payment_box payment_method_buckaroo">
            {/* Conditionally render dropdown or radio buttons */}
            {displayMode === 'dropdown' ? (
                // Dropdown for Mobile View
                <div className="form-row form-row-wide buckaroo-paybybank-mobile">
                    <select className="buckaroo-custom-select" value={selectedIssuer} onChange={(e) => handleSelectIssuer(e.target.value)}>
                        <option value="0">Select your bank</option>
                        {Object.keys(payByBankIssuers).map((issuerCode) => (
                            <option key={issuerCode} value={issuerCode}>
                                {payByBankIssuers[issuerCode].name}
                            </option>
                        ))}
                    </select>
                </div>
            ) : (
                // Radio Buttons for Desktop View
                <div className="bk-paybybank-input bk-paybybank-not-mobile">
                    <div className={`form-row form-row-wide bk-paybybank-selector ${showAllBanks ? 'show-all' : ''}`}>
                        {Object.keys(payByBankIssuers).map((issuerCode) => (
                            <div className="custom-control custom-radio bank-control" key={issuerCode}>
                                <input
                                    type="radio"
                                    id={`radio-bankMethod-${issuerCode}`}
                                    name="buckaroo-paybybank-radio-issuer"
                                    value={issuerCode}
                                    checked={selectedIssuer === issuerCode}
                                    onChange={() => handleSelectIssuer(issuerCode)}
                                    className="custom-control-input bank-method-input bk-paybybank-radio"
                                />
                                <label className="custom-control-label bank-method-label" htmlFor={`radio-bankMethod-${issuerCode}`}>
                                    <img src={buckarooImagesUrl + 'ideal/' +(payByBankIssuers[issuerCode].logo || 'default-logo-filename.png')} width="45" className="bank-method-image" alt={payByBankIssuers[issuerCode].name} />
                                    <strong>{payByBankIssuers[issuerCode].name}</strong>
                                </label>
                            </div>
                        ))}
                    </div>

                    {/* Toggle Button */}
                    <div className="bk-paybybank-toggle-list" onClick={toggleBankView}>
                        <div className="bk-toggle-wrap">
                            <div className="bk-toggle-text">
                                {showAllBanks ? 'More banks' : 'Less banks'}
                            </div>
                            <div className={`bk-toggle ${showAllBanks ? 'bk-toggle-up' : 'bk-toggle-down'}`}></div>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default PayByBankDropdown;
