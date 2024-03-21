import React, { useEffect } from 'react';

export const BuckarooPaypalExpress = ({ billing }) => {
    useEffect(() => {
        if (BuckarooInitPaypalExpress) {
            BuckarooInitPaypalExpress();
        }
    }, []);

    const totalValue = billing.cartTotal.value;
    useEffect(() => {
        document.dispatchEvent(new Event("paypalExpressRefresh"));
    }, [
        totalValue
    ]);

    return (
        <div class="buckaroo-paypal-express"></div>
    );
};