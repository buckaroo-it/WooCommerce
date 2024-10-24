import React, {useEffect, useState} from 'react';
import DefaultPayment from './gateways/default_payment';
import {convertUnderScoreToDash, decodeHtmlEntities} from './utils/utils';
import BuckarooLabel from './components/BuckarooLabel';
import BuckarooApplepay from './components/BuckarooApplepay';
import BuckarooPaypalExpress from './components/BuckarooPaypalExpress';
import BuckarooAfterpay from './gateways/buckaroo_afterpay';
import BuckarooAfterpayNew from './gateways/buckaroo_afterpaynew';
import BuckarooBillink from './gateways/buckaroo_billink';
import BuckarooCreditCard from './gateways/buckaroo_creditcard';
import BuckarooIdeal from './gateways/buckaroo_ideal';
import BuckarooIn3 from './gateways/buckaroo_in3';
import BuckarooKlarnaKP from './gateways/buckaroo_klarnakp';
import BuckarooKlarnaPay from './gateways/buckaroo_klarnapay';
import BuckarooKlarnaPii from './gateways/buckaroo_klarnapii';
import BuckarooPayByBank from './gateways/buckaroo_paybybank';
import BuckarooPayPerEmail from './gateways/buckaroo_payperemail';
import BuckarooSepaDirectDebit from './gateways/buckaroo_sepadirectdebit';
import BuckarooSeparateCreditCard from './gateways/buckaroo_separate_credit_card';
import {__} from '@wordpress/i18n';
import {useDispatch} from '@wordpress/data';

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
  const storeCartDispatch = useDispatch('wc/store/cart');

  useEffect(() => {
    jQuery.ajax({
      url: '/wp-admin/admin-ajax.php',
      type: 'POST',
      data: {
        action: 'woocommerce_cart_calculate_fees',
        method: gateway.paymentMethodId
      },
      success: function () {
        storeCartDispatch.updateCustomerData();
      }
    });
  }, [gateway.paymentMethodId]);

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
        switch (methodId) {
          case 'buckaroo_afterpay':
            LoadedComponent = BuckarooAfterpay;
            break;
          case 'buckaroo_afterpaynew':
            LoadedComponent = BuckarooAfterpayNew;
            break;
          case 'buckaroo_billink':
            LoadedComponent = BuckarooBillink;
            break;
          case 'buckaroo_creditcard':
            LoadedComponent = BuckarooCreditCard;
            break;
          case 'buckaroo_ideal':
            LoadedComponent = BuckarooIdeal;
            break;
          case 'buckaroo_in3':
            LoadedComponent = BuckarooIn3;
            break;
          case 'buckaroo_klarnakp':
            LoadedComponent = BuckarooKlarnaKP;
            break;
          case 'buckaroo_klarnapay':
            LoadedComponent = BuckarooKlarnaPay;
            break;
          case 'buckaroo_klarnapii':
            LoadedComponent = BuckarooKlarnaPii;
            break;
          case 'buckaroo_paybybank':
            LoadedComponent = BuckarooPayByBank;
            break;
          case 'buckaroo_payperemail':
            LoadedComponent = BuckarooPayPerEmail;
            break;
          case 'buckaroo_sepadirectdebit':
            LoadedComponent = BuckarooSepaDirectDebit;
            break;
          default:
            if (separateCreditCards.includes(methodId)) {
              LoadedComponent = BuckarooSeparateCreditCard; // or your credit card handling component
            }
            break;
        }

        setPaymentComponent(() => function () {
          return (
              <LoadedComponent
                  onStateChange={onPaymentStateChange}
                  methodName={methodName}
                  title={decodeHtmlEntities(gateway.title)}
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

        <span className="description">{sprintf(
            __('Pay with %s', 'wc-buckaroo-bpe-gateway'),
            decodeHtmlEntities(gateway.title)
        )}</span>
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
