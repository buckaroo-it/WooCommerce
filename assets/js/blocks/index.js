import React, { useEffect, useState } from 'react';
import DefaultPayment from './gateways/default_payment';
import { convertUnderScoreToDash, decodeHtmlEntities } from './utils/utils';
import BuckarooLabel from './components/BuckarooLabel';
import BuckarooApplepay from './components/BuckarooApplepay';
import BuckarooPaypalExpress from './components/BuckarooPaypalExpress';
import { paymentGatewaysTemplates, separateCreditCards } from './gateways';
import { __ } from '@wordpress/i18n';

function BuckarooComponent({ wc, billing, gateway, eventRegistration, emitResponse }) {
    const [errorMessage, setErrorMessage] = useState('');
    const [PaymentComponent, setPaymentComponent] = useState(null);
    const [activePaymentMethodState, setActivePaymentMethodState] = useState({});
    const methodName = convertUnderScoreToDash(gateway.paymentMethodId);

    useEffect(() => {
        jQuery.ajax({
            url: '/wp-admin/admin-ajax.php',
            type: 'POST',
            data: {
                action: 'woocommerce_cart_calculate_fees',
                method: gateway.paymentMethodId,
            },
        });
    }, [gateway.paymentMethodId]);

    const onPaymentStateChange = newState => {
        setActivePaymentMethodState(newState);
    };

    useEffect(() => {
        const unsubscribe = eventRegistration.onCheckoutFail(props => {
            setErrorMessage(props.processingResponse.paymentDetails.errorMessage);
            return {
                type: emitResponse.responseTypes.FAIL,
                errorMessage: 'Error',
                message: 'Error occurred, please try again',
            };
        });
        return () => unsubscribe();
    }, []);

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
    }, [gateway.paymentMethodId, activePaymentMethodState, billing.billingAddress]);

    // do not refresh component for every change for credit card (causing error with third-party)
    const deps =
        gateway.paymentMethodId === 'buckaroo_creditcard'
            ? []
            : [gateway.paymentMethodId, billing.billingData, methodName];

    useEffect(() => {
        const loadPaymentComponent = async methodId => {
            try {
                let LoadedComponent = DefaultPayment;

                if (typeof paymentGatewaysTemplates[methodId] !== 'undefined') {
                    LoadedComponent = paymentGatewaysTemplates[methodId];
                } else if (separateCreditCards.includes(methodId)) {
                    LoadedComponent = paymentGatewaysTemplates['buckaroo_creditcard'];
                }

                setPaymentComponent(
                    () =>
                        function () {
                            return (
                                <LoadedComponent
                                    onStateChange={onPaymentStateChange}
                                    methodName={methodName}
                                    gateway={gateway}
                                    eventRegistration={eventRegistration}
                                    emitResponse={emitResponse}
                                    setErrorMessage={setErrorMessage}
                                    locale={wc.wcSettings.LOCALE}
                                    billing={billing.billingData}
                                    title={decodeHtmlEntities(gateway.title)}
                                />
                            );
                        }
                );
            } catch (error) {
                console.error(`Error importing payment method module for ${methodId}:`, error);
                setErrorMessage(`Error loading payment component for ${methodId}`);
            }
        };

        loadPaymentComponent(gateway.paymentMethodId);
    }, deps);

    if (!PaymentComponent) {
        return <div>Loading...</div>;
    }

    return (
        <div className="container">
            <span className="description">
                {sprintf(__('Pay with %s', 'wc-buckaroo-bpe-gateway'), decodeHtmlEntities(gateway.title))}
            </span>
            {errorMessage && errorMessage?.length && <div className="woocommerce-error">{errorMessage}</div>}
            <PaymentComponent />
        </div>
    );
}

const getEnabledBuckarooPaymentMethods = () => {
    return (
        window.wc.wcSettings.getPaymentMethodData('buckaroo_express_blocks')?.buckarooGateways ??
        window.buckarooGateways ??
        []
    );
};

const registerBuckarooPaymentMethods = () => {
    const buckarooGateways = getEnabledBuckarooPaymentMethods();
    const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
    buckarooGateways.forEach(gateway => {
        registerPaymentMethod(createOptions(window.wc, gateway));
    });
};

const registerBuckarooExpressPaymentMethods = async () => {
    const buckarooGateways = getEnabledBuckarooPaymentMethods();

    const applepay = buckarooGateways.find(gateway => gateway.paymentMethodId === 'buckaroo_applepay');
    await registerApplePay(applepay);

    const paypalExpress = buckarooGateways.find(gateway => gateway.paymentMethodId === 'buckaroo_paypal');
    await registerPaypalExpress(paypalExpress);
};

const registerPaypalExpress = async gateway => {
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

const registerApplePay = async applepay => {
    if (applepay === undefined) {
        return;
    }

    const checkApplePaySupport = merchantIdentifier => {
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

const createOptions = (wc, gateway) => ({
    name: gateway.paymentMethodId,
    label: <BuckarooLabel imagePath={gateway.image_path} title={decodeHtmlEntities(gateway.title)} />,
    paymentMethodId: gateway.paymentMethodId,
    edit: <div />,
    canMakePayment: () => true,
    ariaLabel: gateway.title,
    content: <BuckarooComponent gateway={gateway} wc={wc} />,
});

const handleBuckarooErrorDisplay = () => {
    const urlParams = new URLSearchParams(window.location.search);
    const bckErr = urlParams.get('bck_err');

    if (!bckErr || !document.querySelector('.wc-block-checkout')) {
        return;
    }

    if (window.wp && window.wp.data && window.wp.data.dispatch) {
        const noticesDispatch = window.wp.data.dispatch('core/notices');
        if (noticesDispatch && noticesDispatch.createNotice) {
            noticesDispatch.createNotice('error', atob(bckErr), {
                context: 'wc/checkout',
                isDismissible: true,
            });
        }
    }
};

registerBuckarooPaymentMethods();
registerBuckarooExpressPaymentMethods();
handleBuckarooErrorDisplay();
