import React, {useContext} from 'react';
import PaymentContext from '../PaymentProvider';

const IdealDropdown = ({methodName, gateway: {idealIssuers}}) => {
    const { updateFormState } = useContext(PaymentContext);

    return (
        <div className="payment_box payment_method_buckaroo_ideal">
            <div className="form-row form-row-wide">
                <select
                    className="buckaroo-custom-select"
                    name="buckaroo-ideal-issuer"
                    id="buckaroo-ideal-issuer"
                    onChange={(e) => updateFormState(`${methodName}-issuer`, e.target.value)}
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
