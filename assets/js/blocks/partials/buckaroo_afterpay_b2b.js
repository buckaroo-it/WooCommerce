import React, { useState } from 'react';

const AfterPayB2B = ({onCocInput,onCompanyInput,onAccountName}) => {


  return (
      <span id="showB2BBuckaroo">
            <p className="form-row form-row-wide validate-required">
                Fill required fields if bill in on the company:
            </p>
         <p className="form-row form-row-wide validate-required">
                <label htmlFor="buckaroo-afterpay-company-coc-registration">
                    COC (KvK) number:<span className="required">*</span>
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
                    Name of the organization:<span className="required">*</span>
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
