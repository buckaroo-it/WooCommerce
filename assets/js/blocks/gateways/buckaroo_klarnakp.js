import React from 'react';
import FinancialWarning from '../partials/buckaroo_financial_warning';

// Gender selection removed from checkout to reduce friction.
// The processor always sends "Unknown" for the mandatory Klarna gender parameter.
function KlarnaKp({ title, gateway: { financialWarning } }) {
    return (
        <div id="buckaroo_klarnakp">
            {financialWarning === 'enable' && <FinancialWarning title={title} />}
        </div>
    );
}

export default KlarnaKp;
