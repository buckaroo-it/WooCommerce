import React, { useEffect } from 'react';

function BuckarooPaypalExpress({ billing }) {
  useEffect(() => {
    if (typeof BuckarooInitPaypalExpress !== 'undefined') {
      BuckarooInitPaypalExpress();
    }
  }, []);

  const totalValue = billing.cartTotal.value;
  useEffect(() => {
    document.dispatchEvent(new Event('paypalExpressRefresh'));
  }, [totalValue]);

  return (
    <div className="buckaroo-paypal-express" />
  );
}

export default BuckarooPaypalExpress;
