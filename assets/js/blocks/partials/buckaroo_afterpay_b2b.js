import React, { useState } from 'react';
import {__} from "@wordpress/i18n";

const AfterPayB2B = ({onCocInput,onCompanyInput,onAccountName}) => {


  return (
      <span id="showB2BBuckaroo">
            <p className="form-row form-row-wide validate-required">
                {__('Fill required fields if bill in on the company:', 'wc-buckaroo-bpe-gateway')}
            </p>
         <p className="form-row form-row-wide validate-required">
                <label htmlFor="buckaroo-afterpay-company-coc-registration">
                    {__('COC (KvK) number:', 'wc-buckaroo-bpe-gateway')}
                    <span className="required">*</span>
                </label>
                <input
                    id="buckaroo-afterpay-company-coc-registration"
                    name="buckaroo-afterpay-company-coc-registration"
                    className="input-text"
                    type="text"
                    maxLength="250"
                    autoComplete="off"
                    onChange={(e) => onCocInput(e.target.value)}
                />
            </p>

            <p className="form-row form-row-wide validate-required">
                <label htmlFor="buckaroo-afterpay-company-name">
                    {__('Name of the organization:', 'wc-buckaroo-bpe-gateway')}
                    <span className="required">*</span>
                </label>
                <input
                    id="buckaroo-afterpay-company-name"
                    name="buckaroo-afterpay-company-name"
                    className="input-text"
                    type="text"
                    maxLength="250"
                    autoComplete="off"
                    onChange={(e) => onCompanyInput(e.target.value)}
                />
            </p>
        </span>
  );
};

export default AfterPayB2B;
