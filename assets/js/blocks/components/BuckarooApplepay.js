import React, { useEffect } from 'react';

function BuckarooApplepay({ billing }) {
  const totalValue = billing.cartTotal.value;

  useEffect(() => {
    document.dispatchEvent(new Event('applepayRefresh'));
  }, [totalValue]);

  useEffect(() => {
    if (window.BuckarooInitApplePay) {
      window.BuckarooInitApplePay();
    }
  }, []);

  return (
    <div className="applepay-button-container"><div /></div>
  );
}

export default BuckarooApplepay;
