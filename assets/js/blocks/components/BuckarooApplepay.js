import React, {useEffect } from 'react';

export const BuckarooApplepay = ({ billing }) => {

    const totalValue =  billing.cartTotal.value;
    useEffect(() => {
        document.dispatchEvent(new Event("applepayRefresh"));
    }, [
        totalValue
    ]);

    return (
        <div class='applepay-button-container'><div></div></div>
    );
};