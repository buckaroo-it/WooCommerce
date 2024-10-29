import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';

function TermsAndConditionsCheckbox({
  paymentMethod, b2b, handleTermsChange, billingData,
}) {
  const [isChecked, setIsChecked] = useState(false);

  const getTermsUrl = (country, isB2B = false) => {
    const baseUrl = 'https://documents.riverty.com/terms_conditions/payment_methods/';
    const languageMap = {
      DE: 'de_de',
      NL: 'nl_nl',
      BE: 'be_nl',
      AT: 'de_at',
      NO: 'no_en',
      FI: 'fi_en',
      SE: 'se_en',
      CH: 'ch_en',
    };

    const languageCode = languageMap[country] || 'nl_en';
    const path = isB2B ? 'b2b_invoice' : 'invoice';

    return `${baseUrl}${path}/${languageCode}/`;
  };

  const fieldName = paymentMethod === 'buckaroo_afterpaynew' ? 'buckaroo-afterpaynew-accept' : paymentMethod === 'buckaroo_afterpay' ? 'buckaroo-afterpay-accept' : paymentMethod;
  const { country } = billingData;
  let labelText = __('Accept Riverty conditions:', 'wc-buckaroo-bpe-gateway');
  let termsUrl = getTermsUrl(country, b2b);

  if (paymentMethod === 'buckaroo-billink') {
    labelText = __('Accept terms of use', 'wc-buckaroo-bpe-gateway');
    termsUrl = 'https://www.billink.nl/app/uploads/2021/05/Gebruikersvoorwaarden-Billink_V11052021.pdf';
  }
  const handleCheckboxChange = () => {
    setIsChecked(!isChecked);
    handleTermsChange(!isChecked);
  };

  return (
    <div>
      <a href={`${termsUrl}`} target="_blank" rel="noreferrer">{labelText}</a>
      <span className="required">*</span>
      <input
        id={`${fieldName}-accept`}
        name={`${fieldName}-accept`}
        type="checkbox"
        checked={isChecked}
        onChange={handleCheckboxChange}
      />
      <p className="required" style={{ float: 'right' }}>
        *
        Required
      </p>
    </div>
  );
}

export default TermsAndConditionsCheckbox;
