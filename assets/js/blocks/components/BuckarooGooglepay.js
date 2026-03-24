import React, { useEffect } from 'react';

function BuckarooGooglepay({ billing }) {
    const totalValue = billing.cartTotal.value;

    useEffect(() => {
        document.dispatchEvent(new Event('googlepayRefresh'));
    }, [totalValue]);

    useEffect(() => {
        if (window.BuckarooInitGooglePay) {
            window.BuckarooInitGooglePay();
        }
    }, []);

    return (
        <div className="googlepay-button-container">
            <div id="googlepay-button-element" />
        </div>
    );
}

export default BuckarooGooglepay;
