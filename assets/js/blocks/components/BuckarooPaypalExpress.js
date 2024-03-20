import React, { useEffect } from 'react';

export const BuckarooPaypalExpress = () => {
    useEffect(() => {
        if (BuckarooInitPaypalExpress) {
            BuckarooInitPaypalExpress();
        }
    }, []);

    return (
        <div class="buckaroo-paypal-express"></div>
    );
};