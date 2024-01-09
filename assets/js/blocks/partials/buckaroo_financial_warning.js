import React from 'react';
const FinancialWarning = ({ title }) => {
    return (
        <div style={{ display: 'block', fontSize: '.8rem', clear: 'both' }}>
            Je moet minimaal 18+ zijn om deze dienst te gebruiken. Als je op tijd betaalt, voorkom je extra kosten en zorg je dat je in de toekomst nogmaals gebruik kunt maken van de diensten van {title}. Door verder te gaan, accepteer je de Algemene Voorwaarden en bevestig je dat je de Privacyverklaring en Cookieverklaring hebt gelezen.
        </div>
    );
};


export default FinancialWarning;
