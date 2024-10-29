import React from 'react';
import useFormData from '../hooks/useFormData';
import { __ } from '@wordpress/i18n';

function IdealDropdown({ onStateChange, methodName, gateway: { idealIssuers, canShowIssuers } }) {
  const initialState = {
    [`${methodName}-issuer`]: '',
  };

  const { handleChange } = useFormData(initialState, onStateChange);
  return (
    canShowIssuers && (
    <div className="payment_box payment_method_buckaroo_ideal">
      <div className="form-row form-row-wide">
        <select
          className="buckaroo-custom-select"
          name="buckaroo-ideal-issuer"
          id="buckaroo-ideal-issuer"
          onChange={handleChange}
        >
          <option value="">{__('Select your bank', 'wc-buckaroo-bpe-gateway')}</option>

          {Object.keys(idealIssuers).map((issuerCode) => (
            <option key={issuerCode} value={issuerCode}>
              {idealIssuers[issuerCode].name}
            </option>
          ))}
        </select>
      </div>
    </div>
    )
  );
}

export default IdealDropdown;
