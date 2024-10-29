import React from 'react';
import { __ } from '@wordpress/i18n';

function FinancialWarning({ title }) {
  const translatedMessage = sprintf(
      __('You must be at least 18+ to use this service. By paying on time, you avoid extra costs and ensure that you can use %s services again in the future. By continuing, you accept the Terms and Conditions and confirm that you have read the Privacy Statement and Cookie Statement.', 'wc-buckaroo-bpe-gateway'),
      title
  );

  return (
    <div style={{ display: 'block', fontSize: '.8rem', clear: 'both' }}>
      {translatedMessage}
    </div>
  );
}

export default FinancialWarning;
