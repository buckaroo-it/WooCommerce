import React from 'react';
import { __ } from '@wordpress/i18n';

function FinancialWarning({ title }) {
  return (
    <div style={{ display: 'block', fontSize: '.8rem', clear: 'both' }}>
      {__('Je moet minimaal 18+ zijn om deze dienst te gebruiken. Als je op tijd betaalt, voorkom je extra kosten en zorg je dat je in de toekomst nogmaals gebruik kunt maken van de diensten van {title}. Door verder te gaan, accepteer je de Algemene Voorwaarden en bevestig je dat je de Privacyverklaring en Cookieverklaring hebt gelezen.', 'wc-buckaroo-bpe-gateway')}
    </div>
  );
}

export default FinancialWarning;
