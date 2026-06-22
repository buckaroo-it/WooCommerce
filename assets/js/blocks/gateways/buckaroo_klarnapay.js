import React from 'react';
import FinancialWarning from '../partials/buckaroo_financial_warning';

// Gender selection removed from checkout to reduce friction.
// The processor always sends "Unknown" for the mandatory Klarna gender parameter.
function KlarnaPay({ title, gateway: { financialWarning } }) {
    return <div id="buckaroo_klarnapay">{financialWarning === 'enable' && <FinancialWarning title={title} />}</div>;
}

export default KlarnaPay;
