import React from 'react';

const Ideal = ({ issuers }) => {
    return (
        <fieldset style={{ background: 'none' }}>
            <p className="form-row form-row-wide">
                <select name="buckaroo-ideal-issuer" id="buckaroo-ideal-issuer">
                    <option value="0" style={{ color: 'grey' }}>
                        Select your bank
                    </option>
                    {issuers.map((issuer, index) => (
                        <option key={index} value={issuer.key}>
                            {issuer.name}
                        </option>
                    ))}
                </select>
            </p>
        </fieldset>
    );
};

export default Ideal;