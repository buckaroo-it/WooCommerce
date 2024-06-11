import React, { useEffect } from 'react';

export function BuckarooApplepay({ billing }) {
  const totalValue = billing.cartTotal.value;
  useEffect(() => {
    document.dispatchEvent(new Event('applepayRefresh'));
  }, [
    totalValue,
  ]);

  useEffect(() => {
    if (BuckarooInitApplePay) {
      BuckarooInitApplePay();
    }
  }, []);

  return (
    <div className="applepay-button-container"><div /></div>
  );
}
