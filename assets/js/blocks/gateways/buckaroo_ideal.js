import React from 'react';
import useFormData from "../hooks/useFormData";

const IdealDropdown = ({onStateChange, methodName, gateway: {idealIssuers}}) => {
    const formFieldName = `${methodName}-issuer`;

    const [formState, updateFormState] = useFormData({[formFieldName]: ''}, onStateChange);
    const handleChange = (e) => {
        const {value} = e.target;
        updateFormState(formFieldName, value);
    };

    return (
        <div className="payment_box payment_method_buckaroo_ideal">
            <div className="form-row form-row-wide">
                <select
                    className="buckaroo-custom-select"
                    name="buckaroo-ideal-issuer"
                    id="buckaroo-ideal-issuer"
                    onChange={handleChange}
                    value={formState[formFieldName]}
                >
                    <option value="">Select your bank</option>
                    {Object.entries(idealIssuers).map(([issuerCode, {name}]) => (
                        <option key={issuerCode} value={issuerCode}>
                            {name}
                        </option>
                    ))}
                </select>
            </div>
        </div>
    );
};

export default IdealDropdown;
