import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { SelectField } from '../components/CheckoutFields';

function PayByBankDropdown({
    onStateChange,
    methodName,
    gateway: { payByBankIssuers, displayMode, buckarooImagesUrl },
}) {
    const [selectedIssuer, setSelectedIssuer] = useState('');

    const handleChange = e => {
        const { value } = e.target;
        setSelectedIssuer(value);
        onStateChange({ [`${methodName}-issuer`]: value });
    };

    // The empty option keeps the select unchosen until the shopper picks a bank.
    const issuerOptions = [
        { value: '', label: '' },
        ...Object.keys(payByBankIssuers).map(issuerCode => ({
            value: issuerCode,
            label: payByBankIssuers[issuerCode].name,
        })),
    ];

    const [showAllBanks, setShowAllBanks] = useState(false);

    const toggleBankView = () => {
        setShowAllBanks(!showAllBanks);
    };

    return (
        <div className="payment_box payment_method_buckaroo">
            {/* Conditionally render dropdown or radio buttons */}
            {displayMode === 'dropdown' ? (
                // Dropdown for Mobile View
                <div className="buckaroo-paybybank-mobile">
                    <SelectField
                        id={`${methodName}-issuer`}
                        label={__('Select your bank', 'wc-buckaroo-bpe-gateway')}
                        options={issuerOptions}
                        value={selectedIssuer}
                        onChange={handleChange}
                    />
                </div>
            ) : (
                <div>
                    <div className="bk-paybybank-input bk-paybybank-mobile" style={{ display: 'none' }}>
                        <SelectField
                            id={`${methodName}-issuer`}
                            label={__('Select your bank', 'wc-buckaroo-bpe-gateway')}
                            options={issuerOptions}
                            value={selectedIssuer}
                            onChange={handleChange}
                        />
                    </div>
                    <div className="bk-paybybank-input bk-paybybank-not-mobile">
                        <div className={`bk-paybybank-selector ${showAllBanks ? 'show-all' : ''}`}>
                            {Object.keys(payByBankIssuers).map(issuerCode => (
                                <div className="custom-control custom-radio bank-control" key={issuerCode}>
                                    <input
                                        type="radio"
                                        id={`radio-bankMethod-${issuerCode}`}
                                        name="buckaroo-paybybank-radio-issuer"
                                        value={issuerCode}
                                        checked={selectedIssuer === issuerCode}
                                        onChange={handleChange}
                                        className="custom-control-input bank-method-input bk-paybybank-radio"
                                    />
                                    <label
                                        className="custom-control-label bank-method-label"
                                        htmlFor={`radio-bankMethod-${issuerCode}`}
                                    >
                                        <img
                                            src={`${buckarooImagesUrl}ideal/${
                                                payByBankIssuers[issuerCode].logo || 'default-logo-filename.png'
                                            }`}
                                            className="bank-method-image"
                                            alt={payByBankIssuers[issuerCode].name}
                                        />
                                        <strong>{payByBankIssuers[issuerCode].name}</strong>
                                    </label>
                                </div>
                            ))}
                        </div>

                        {/* Toggle Button */}
                        <div className="bk-paybybank-toggle-list" onClick={toggleBankView}>
                            <div className="bk-toggle-wrap">
                                <div className="bk-toggle-text">
                                    {showAllBanks
                                        ? __('More banks', 'wc-buckaroo-bpe-gateway')
                                        : __('Less banks', 'wc-buckaroo-bpe-gateway')}
                                </div>
                                <div className={`bk-toggle ${showAllBanks ? 'bk-toggle-up' : 'bk-toggle-down'}`} />
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

export default PayByBankDropdown;
