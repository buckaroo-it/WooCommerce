import React from 'react';
import useFormData from "../hooks/useFormData";

const IdealDropdown = ({onStateChange, methodName, gateway: {idealIssuers}}) => {

    const initialState = {
        [`${methodName}-issuer`]: '',
    };

    const [formState, handleChange] = useFormData(initialState, onStateChange);

    return (
        <div className="payment_box payment_method_buckaroo_ideal">
            <div className="form-row form-row-wide">
                <select
                    className="buckaroo-custom-select"
                    name="buckaroo-ideal-issuer"
                    id="buckaroo-ideal-issuer"
                    onChange={handleChange}
                >
                    <option value="">Select your bank</option>

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