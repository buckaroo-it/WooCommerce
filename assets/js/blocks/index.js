import React, { useEffect, useState } from 'react';
import DefaultPayment from './gateways/default_payment';
import { convertUnderScoreToDash, decodeHtmlEntities } from './utils/utils';
import BuckarooLabel from './components/BuckarooLabel';
import BuckarooApplepay from './components/BuckarooApplepay';
import BuckarooPaypalExpress from './components/BuckarooPaypalExpress';

const customTemplatePaymentMethodIds = [
  'buckaroo_afterpay', 'buckaroo_afterpaynew', 'buckaroo_billink', 'buckaroo_creditcard',
  'buckaroo_ideal', 'buckaroo_in3', 'buckaroo_klarnakp', 'buckaroo_klarnapay',
  'buckaroo_klarnapii', 'buckaroo_paybybank', 'buckaroo_payperemail', 'buckaroo_sepadirectdebit',
];

const separateCreditCards = [
  'buckaroo_creditcard_amex',
  'buckaroo_creditcard_cartebancaire',
  'buckaroo_creditcard_cartebleuevisa',
  'buckaroo_creditcard_dankort',
  'buckaroo_creditcard_maestro',
  'buckaroo_creditcard_mastercard',
  'buckaroo_creditcard_nexi',
  'buckaroo_creditcard_postepay',
  'buckaroo_creditcard_visa',
  'buckaroo_creditcard_visaelectron',
  'buckaroo_creditcard_vpay',
];

function BuckarooComponent({
  billing, gateway, eventRegistration, emitResponse,
}) {
  const [errorMessage, setErrorMessage] = useState('');
  const [PaymentComponent, setPaymentComponent] = useState(null);
  const [activePaymentMethodState, setActivePaymentMethodState] = useState({});
  const methodName = convertUnderScoreToDash(gateway.paymentMethodId);

  const onPaymentStateChange = (newState) => {
    setActivePaymentMethodState(newState);
  };

  useEffect(() => {
    const unsubscribe = eventRegistration.onCheckoutFail((props) => {
      setErrorMessage(props.processingResponse.paymentDetails.errorMessage);
      return {
        type: emitResponse.responseTypes.FAIL,
        errorMessage: 'Error',
        message: 'Error occurred, please try again',
      };
    });
    return () => unsubscribe();
  }, [eventRegistration, emitResponse]);

  useEffect(() => {
    const unsubscribe = eventRegistration.onPaymentSetup(() => {
      const response = {
        type: emitResponse.responseTypes.SUCCESS, meta: {},
      };

      response.meta.paymentMethodData = {
        ...activePaymentMethodState,
        isblocks: '1',
        billing_company: billing.billingAddress.company,
        billing_country: billing.billingAddress.country,
        billing_address_1: billing.billingAddress.address_1,
        billing_address_2: billing.billingAddress.address_2,
      };

      return response;
    });
    return () => unsubscribe();
  }, [eventRegistration, emitResponse, gateway.paymentMethodId, activePaymentMethodState, billing.billingAddress]);

  useEffect(() => {
    const loadPaymentComponent = async (methodId) => {
      try {
        let LoadedComponent = DefaultPayment;
        if (customTemplatePaymentMethodIds.includes(methodId)) {
          ({ default: LoadedComponent } = await import(`./gateways/${methodId}`));
        } else if (separateCreditCards.includes(methodId)) {
          ({ default: LoadedComponent } = await import('./gateways/buckaroo_separate_credit_card'));
        }
        setPaymentComponent(() => function () {
          return (
            <LoadedComponent
              onStateChange={onPaymentStateChange}
              methodName={methodName}
              gateway={gateway}
              billing={billing.billingData}
            />
          );
        });
      } catch (error) {
        console.error(`Error importing payment method module for ${methodId}:`, error);
        setErrorMessage(`Error loading payment component for ${methodId}`);
      }
    };

    loadPaymentComponent(gateway.paymentMethodId);
  }, [gateway.paymentMethodId, billing.billingData, methodName]);

  if (!PaymentComponent) {
    return <div>Loading...</div>;
  }

  return (
    <div className="container">
      <span className="description">{gateway.description}</span>
      <span className="descriptionError">{errorMessage}</span>
      <PaymentComponent />
    </div>
  );
}

const registerBuckarooPaymentMethods = ({ wc, buckarooGateways }) => {
  const { registerPaymentMethod } = wc.wcBlocksRegistry;
  buckarooGateways.forEach((gateway) => {
    registerPaymentMethod(createOptions(gateway));
  });
};

const registerBuckarooExpressPaymentMethods = async ({ buckarooGateways }) => {
  const ready = async () => new Promise((resolve) => {
    document.addEventListener('bk-jquery-loaded', () => resolve(true), { once: true });
    setTimeout(() => resolve(false), 5000);
  });

  if (!await ready()) {
    return;
  }

  const applepay = buckarooGateways.find((gateway) => gateway.paymentMethodId === 'buckaroo_applepay');
  await registerApplePay(applepay);

  const paypalExpress = buckarooGateways.find((gateway) => gateway.paymentMethodId === 'buckaroo_paypal');
  await registerPaypalExpress(paypalExpress);
};

const registerPaypalExpress = async (gateway) => {
  if (gateway === undefined) {
    return;
  }

  if (gateway.showInCheckout) {
    const { registerExpressPaymentMethod } = wc.wcBlocksRegistry;

    registerExpressPaymentMethod({
      name: 'buckaroo_paypal_express',
      content: <BuckarooPaypalExpress />,
      edit: <div />,
      canMakePayment: () => true,
      paymentMethodId: 'buckaroo_paypal_express',
    });
  }
};

const registerApplePay = async (applepay) => {
  if (applepay === undefined) {
    return;
  }

  const checkApplePaySupport = (merchantIdentifier) => {
    if (!('ApplePaySession' in window)) return Promise.resolve(false);
    if (ApplePaySession === undefined) return Promise.resolve(false);
    return ApplePaySession.canMakePaymentsWithActiveCard(merchantIdentifier);
  };

  const canDisplay = await checkApplePaySupport(applepay.merchantIdentifier);
  if (applepay.showInCheckout && canDisplay) {
    const { registerExpressPaymentMethod } = wc.wcBlocksRegistry;

    registerExpressPaymentMethod({
      name: 'buckaroo_express_applepay',
      content: <BuckarooApplepay />,
      edit: <div />,
      canMakePayment: () => true,
      paymentMethodId: 'buckaroo_express_applepay',
    });
  }
};

const createOptions = (gateway) => ({
  name: gateway.paymentMethodId,
  label: <BuckarooLabel imagePath={gateway.image_path} title={decodeHtmlEntities(gateway.title)} />,
  paymentMethodId: gateway.paymentMethodId,
  edit: <div />,
  canMakePayment: () => true,
  ariaLabel: gateway.title,
  content: <BuckarooComponent gateway={gateway} />,
});

registerBuckarooPaymentMethods(window);

(async () => {
  await registerBuckarooExpressPaymentMethods(window);
})();
