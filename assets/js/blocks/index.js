import React, { useEffect, useState } from 'react';
import DefaultPayment from './gateways/default_payment';
import { convertUnderScoreToDash, decodeHtmlEntities } from './utils/utils';
import BuckarooLabel from './components/BuckarooLabel';
import BuckarooApplepay from './components/BuckarooApplepay';
import BuckarooPaypalExpress from './components/BuckarooPaypalExpress';

const customTemplatePaymentMethodIds = [
  'buckaroo_afterpay',
  'buckaroo_afterpaynew',
  'buckaroo_billink',
  'buckaroo_creditcard',
  'buckaroo_ideal',
  'buckaroo_in3',
  'buckaroo_klarnakp',
  'buckaroo_klarnapay',
  'buckaroo_klarnapii',
  'buckaroo_paybybank',
  'buckaroo_payperemail',
  'buckaroo_sepadirectdebit',
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
        type: emitResponse.responseTypes.SUCCESS,
        meta: {},
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
        setPaymentComponent(() => LoadedComponent);
      } catch (error) {
        console.error(`Error importing payment method module for ${methodId}:`, error);
        setErrorMessage(`Error loading payment component for ${methodId}`);
      }
    };

    loadPaymentComponent(gateway.paymentMethodId);
  }, [gateway.paymentMethodId, billing.billingData]);

  if (!PaymentComponent) {
    return <div>Loading...</div>;
  }

  return (
    <div className="container">
      <span className="description">{gateway.description}</span>
      <span className="descriptionError">{errorMessage}</span>
      <PaymentComponent
        onStateChange={onPaymentStateChange}
        methodName={methodName}
        gateway={gateway}
        billing={billing.billingData}
      />
    </div>
  );
}

BuckarooComponent.displayName = 'BuckarooComponent';

const createOptions = (gateway, Component) => ({
  name: gateway.paymentMethodId,
  label: <BuckarooLabel imagePath={gateway.image_path} title={decodeHtmlEntities(gateway.title)} />,
  paymentMethodId: gateway.paymentMethodId,
  edit: <div />,
  canMakePayment: () => true,
  ariaLabel: gateway.title,
  content: <Component gateway={gateway} />,
});

const registerApplePay = async (applepay, wc) => {
  if (applepay === undefined) {
    return;
  }

  const checkApplePaySupport = (merchantIdentifier) => {
    if (!('ApplePaySession' in window)) return Promise.resolve(false);
    if (window.ApplePaySession === undefined) return Promise.resolve(false);
    return window.ApplePaySession.canMakePaymentsWithActiveCard(merchantIdentifier);
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

const registerPaypalExpress = async (gateway, wc) => {
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

const registerBuckarooPaymentMethods = ({ wc, buckarooGateways }) => {
  const { registerPaymentMethod } = wc.wcBlocksRegistry;
  if (!Array.isArray(buckarooGateways)) {
    console.error('buckarooGateways is not an array or is undefined:', buckarooGateways);
    return;
  }
  buckarooGateways.forEach((gateway) => {
    registerPaymentMethod(createOptions(gateway, BuckarooComponent));
  });
};

const registerBuckarooExpressPaymentMethods = async ({ wc, buckarooGateways }) => {
  const ready = async () => new Promise((resolve) => {
    document.addEventListener('bk-jquery-loaded', () => resolve(true), { once: true });
    setTimeout(() => resolve(false), 5000);
  });
  if (!(await ready())) {
    return;
  }

  const applepay = buckarooGateways.find((gateway) => gateway.paymentMethodId === 'buckaroo_applepay');
  await registerApplePay(applepay, wc);

  const paypalExpress = buckarooGateways.find((gateway) => gateway.paymentMethodId === 'buckaroo_paypal');

  await registerPaypalExpress(paypalExpress, wc);
};

(async () => {
  registerBuckarooPaymentMethods(window);
  await registerBuckarooExpressPaymentMethods(window);
})();
