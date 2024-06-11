import React from 'react';
import { __ } from '@wordpress/i18n';
import useFormData from '../hooks/useFormData';

function SepaDirectDebitForm({ onStateChange, methodName, billing }) {
  const initialState = {
    [`${methodName}-accountname`]: `${billing.first_name} ${billing.last_name}`,
    [`${methodName}-iban`]: '',
    [`${methodName}-bic`]: '',
  };

  const { formState, handleChange } = useFormData(initialState, onStateChange);

  return (
    <div>
      <div className="form-row form-row-wide validate-required">
        <label htmlFor="buckaroo-sepadirectdebit-accountname">
          <span className="required">*</span>
          {__('Bank account holder:', 'wc-buckaroo-bpe-gateway')}
        </label>
        <input
          id="buckaroo-sepadirectdebit-accountname"
          name="buckaroo-sepadirectdebit-accountname"
          className="input-text"
          type="text"
          maxLength="250"
          autoComplete="off"
          value={formState[`${methodName}-accountname`]}
          onChange={handleChange}
        />
      </div>
      <div className="form-row form-row-wide validate-required">
        <label htmlFor="buckaroo-sepadirectdebit-iban">
          {__('IBAN:', 'wc-buckaroo-bpe-gateway')}
          <span className="required">*</span>
        </label>
        <input
          id="buckaroo-sepadirectdebit-iban"
          name="buckaroo-sepadirectdebit-iban"
          className="input-text"
          type="text"
          maxLength="25"
          autoComplete="off"
          value={formState[`${methodName}-iban`]}
          onChange={handleChange}
        />
      </div>
      <div className="form-row form-row-wide">
        <label htmlFor="buckaroo-sepadirectdebit-bic">
          {__('BIC:', 'wc-buckaroo-bpe-gateway')}
        </label>
        <input
          id="buckaroo-sepadirectdebit-bic"
          name="buckaroo-sepadirectdebit-bic"
          className="input-text"
          type="text"
          maxLength="11"
          autoComplete="off"
          value={formState[`${methodName}-bic`]}
          onChange={handleChange}
        />
      </div>
      <div className="required" style={{ float: 'right' }}>
        *
        {__('Required', 'wc-buckaroo-bpe-gateway')}
      </div>
      <br />
    </div>
  );
}

export default SepaDirectDebitForm;
