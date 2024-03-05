import React, {useState, useContext} from 'react';
import PaymentContext from '../PaymentProvider';

const PayByBankDropdown = ({
        methodName,
        gateway: {payByBankIssuers, displayMode, buckarooImagesUrl}
    }) => {
    const { updateFormState } = useContext(PaymentContext);
    const [selectedIssuer, setSelectedIssuer] = useState('');

    const handleChange = (e) => {
        setSelectedIssuer(e.target.value);
        updateFormState(`${methodName}-issuer`, e.target.value);
    };

    const [showAllBanks, setShowAllBanks] = useState(false);

    const toggleBankView = () => {
        setShowAllBanks(!showAllBanks);
    };

    return (
        <div className="payment_box payment_method_buckaroo">
            {/* Conditionally render dropdown or radio buttons */}
            {displayMode === 'dropdown' ? (
                // Dropdown for Mobile View
                <div className="form-row form-row-wide buckaroo-paybybank-mobile">
                    <select className="buckaroo-custom-select" value={selectedIssuer} onChange={handleChange}>
                        <option value="0">Select your bank</option>
                        {Object.keys(payByBankIssuers).map((issuerCode) => (
                            <option key={issuerCode} value={issuerCode}>
                                {payByBankIssuers[issuerCode].name}
                            </option>
                        ))}
                    </select>
                </div>
            ) : (
                <div>
                    <div className="form-row form-row-wide bk-paybybank-input bk-paybybank-mobile"
                         style={{display: 'none'}}>
                        <select className="buckaroo-custom-select" value={selectedIssuer}
                                onChange={handleChange}>
                            <option value="0">Select your bank</option>
                            {Object.keys(payByBankIssuers).map((issuerCode) => (
                                <option key={issuerCode} value={issuerCode}>
                                    {payByBankIssuers[issuerCode].name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="bk-paybybank-input bk-paybybank-not-mobile">
                        <div
                            className={`form-row form-row-wide bk-paybybank-selector ${showAllBanks ? 'show-all' : ''}`}>
                            {Object.keys(payByBankIssuers).map((issuerCode) => (
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
                                    <label className="custom-control-label bank-method-label"
                                           htmlFor={`radio-bankMethod-${issuerCode}`}>
                                        <img
                                            src={buckarooImagesUrl + 'ideal/' + (payByBankIssuers[issuerCode].logo || 'default-logo-filename.png')}
                                            className="bank-method-image" alt={payByBankIssuers[issuerCode].name}/>
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
                </div>
            )}
        </div>
    );
};

export default PayByBankDropdown;
