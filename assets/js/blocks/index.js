import React, {useEffect, useState} from 'react';
import DefaultPayment from "./gateways/default_payment";
import {convertUnderScoreToDash, decodeHtmlEntities} from './utils/utils';
import {BuckarooLabel} from "./components/BuckarooLabel";
import {BuckarooApplepay} from "./components/BuckarooApplepay";

const customTemplatePaymentMethodIds = [
    'buckaroo_afterpay', 'buckaroo_afterpaynew', 'buckaroo_billink', 'buckaroo_creditcard',
    'buckaroo_ideal', 'buckaroo_in3', 'buckaroo_klarnakp', 'buckaroo_klarnapay',
    'buckaroo_klarnapii', 'buckaroo_paybybank', 'buckaroo_payperemail', 'buckaroo_sepadirectdebit'
];

const separateCreditCards = [
    "buckaroo_creditcard_amex",
    "buckaroo_creditcard_cartebancaire",
    "buckaroo_creditcard_cartebleuevisa",
    "buckaroo_creditcard_dankort",
    "buckaroo_creditcard_maestro",
    "buckaroo_creditcard_mastercard",
    "buckaroo_creditcard_nexi",
    "buckaroo_creditcard_postepay",
    "buckaroo_creditcard_visa",
    "buckaroo_creditcard_visaelectron",
    "buckaroo_creditcard_vpay",
];

const BuckarooComponent = ({billing, gateway, eventRegistration, emitResponse}) => {
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
            let response = {
                type: emitResponse.responseTypes.SUCCESS, meta: {},
            };

            response.meta.paymentMethodData = {
                ...activePaymentMethodState,
                'isblocks': '1',
                'billing_country': billing.billingAddress.country,
                'billing_address_1': billing.billingAddress.address_1,
                'billing_address_2': billing.billingAddress.address_2,
            };

            return response;
        });
        return () => unsubscribe();
    }, [eventRegistration, emitResponse, gateway.paymentMethodId]);


    useEffect(() => {
        const loadPaymentComponent = async (methodId) => {
            try {
                let LoadedComponent = DefaultPayment;
                if (customTemplatePaymentMethodIds.includes(methodId)) {
                    ({default: LoadedComponent} = await import(`./gateways/${methodId}`));
                } else if (separateCreditCards.includes(methodId)) {
                    ({default: LoadedComponent} = await import(`./gateways/buckaroo_separate_credit_card`));
                }
                setPaymentComponent(() => () => <LoadedComponent onStateChange={onPaymentStateChange}
                                                                 methodName={methodName} gateway={gateway}
                                                                 billing={billing.billingData}/>);
            } catch (error) {
                console.error(`Error importing payment method module for ${methodId}:`, error);
                setErrorMessage(`Error loading payment component for ${methodId}`);
            }
        };

        loadPaymentComponent(gateway.paymentMethodId);
    }, [gateway.paymentMethodId]);

    if (!PaymentComponent) {
        return <div>Loading...</div>;
    }

    return (
        <div className='container'>
            <span className='description'>{gateway.description}</span>
            <span className='descriptionError'>{errorMessage}</span>
            <PaymentComponent/>
        </div>
    );
}

const registerBuckarooPaymentMethods = ({wc, buckaroo_gateways}) => {
    const {registerPaymentMethod} = wc.wcBlocksRegistry;
    buckaroo_gateways.forEach((gateway) => {
        registerPaymentMethod(createOptions(gateway, BuckarooComponent));
    });
}
const registerBuckarooExpressPaymentMethods = async ({ wc, buckaroo_gateways }) => {
    const applepay = buckaroo_gateways.find((gateway) => {
        return gateway.paymentMethodId === "buckaroo_applepay";
    })

    if (applepay === undefined) {
        return;
    }

    const canDisplay = await window?.ApplePaySession?.canMakePaymentsWithActiveCard(applepay.merchantIdentifier);
    if (applepay.showInCheckout && canDisplay) {
        const { registerExpressPaymentMethod } = wc.wcBlocksRegistry;

        registerExpressPaymentMethod({
            name: 'buckaroo_express_applepay',
            content: <BuckarooApplepay />,
            edit: <div />,
            canMakePayment: () => true,
            paymentMethodId: 'buckaroo_express_applepay',
        })
    }
}
const createOptions = (gateway, BuckarooComponent) => {
    return {
        name: gateway.paymentMethodId,
        label: <BuckarooLabel image_path={gateway.image_path} title={decodeHtmlEntities(gateway.title)}/>,
        paymentMethodId: gateway.paymentMethodId,
        edit: <div/>,
        canMakePayment: () => true,
        ariaLabel: gateway.title,
        content: <BuckarooComponent gateway={gateway}/>
    }
}

registerBuckarooPaymentMethods(window)
await registerBuckarooExpressPaymentMethods(window);