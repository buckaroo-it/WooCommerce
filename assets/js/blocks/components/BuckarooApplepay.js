import React, { useEffect } from 'react';

export const BuckarooApplepay = ({ billing }) => {

    const totalValue = billing.cartTotal.value;
    useEffect(() => {
        document.dispatchEvent(new Event("applepayRefresh"));
    }, [
        totalValue
    ]);


    useEffect(() => {
        if (BuckarooInitApplePay) {
            BuckarooInitApplePay();
        }
    }, []);

    return (
        <div class='applepay-button-container'><div></div></div>
    );
};