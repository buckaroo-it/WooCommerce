import React from 'react';
import { __ } from '@wordpress/i18n';
import GenderDropdown from '../partials/buckaroo_gender';
import useFormData from '../hooks/useFormData';

function PayPerEmailForm({
  onStateChange, methodName, gateway: { genders }, billing,
}) {
  const initialState = {
    [`${methodName}-firstname`]: billing?.first_name || '',
    [`${methodName}-lastname`]: billing?.last_name || '',
    [`${methodName}-email`]: billing?.email || '',
    [`${methodName}-gender`]: '',
  };

  const { formState, handleChange } = useFormData(initialState, onStateChange);

  return (
    <div>
      <GenderDropdown
        paymentMethod={methodName}
        genders={genders}
        handleChange={handleChange}
      />

      <div className="form-row validate-required">
        <label htmlFor="buckaroo-payperemail-firstname">
          {__('First Name:', 'wc-buckaroo-bpe-gateway')}
          <span className="required">*</span>
        </label>
        <input
          id="buckaroo-payperemail-firstname"
          name="buckaroo-payperemail-firstname"
          className="input-text"
          type="text"
          autoComplete="off"
          value={formState[`${methodName}-firstname`] || ''}
          onChange={handleChange}
        />
      </div>

      <div className="form-row validate-required">
        <label htmlFor="buckaroo-payperemail-lastname">
          {__('Last Name:', 'wc-buckaroo-bpe-gateway')}
          <span className="required">*</span>
        </label>
        <input
          id="buckaroo-payperemail-lastname"
          name="buckaroo-payperemail-lastname"
          className="input-text"
          type="text"
          autoComplete="off"
          value={formState[`${methodName}-lastname`] || ''}
          onChange={handleChange}
        />
      </div>

      <div className="form-row validate-required">
        <label htmlFor="buckaroo-payperemail-email">
          {__('Email:', 'wc-buckaroo-bpe-gateway')}
          <span className="required">*</span>
        </label>
        <input
          id="buckaroo-payperemail-email"
          name="buckaroo-payperemail-email"
          className="input-text"
          type="email"
          autoComplete="off"
          value={formState[`${methodName}-email`] || ''}
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

export default PayPerEmailForm;
