import React, {useEffect, useState, Suspense, useContext} from 'react';
import {convertUnderScoreToDash, decodeHtmlEntities} from './utils/utils';
import {BuckarooLabel} from "./components/BuckarooLabel";
import PaymentContext, { defaults } from "./PaymentProvider"

const customTemplatePaymentMethodIds = [
    'buckaroo_afterpay', 'buckaroo_afterpaynew', 'buckaroo_billink',
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
    'buckaroo_creditcard',
];

const BuckarooComponent = ({billing, gateway, eventRegistration, emitResponse}) => {
    const [errorMessage, setErrorMessage] = useState('');
    const methodName = convertUnderScoreToDash(gateway.paymentMethodId);
    const { onCheckoutFail, onPaymentSetup } = eventRegistration;

    const methodId = gateway.paymentMethodId.replace("_","-")
    const defaultState = defaults[methodId] ?? {};

    const [state, setState] = useState(defaultState);

    const updateFormState = (fieldName, value) => {
        setState({ ...state, [fieldName]: value });
    };

    const updateMultiple = (newState) => {
        setState({ ...state, ...newState });
    }

    const handleChange = (e) => {
        const { name, value } = e.target;
        if (name) {
            updateFormState(name, value);
        }
    };

    useEffect(() => {
        const unsubscribe = onCheckoutFail((props) => {
            setErrorMessage(props.processingResponse.paymentDetails.errorMessage);
            return {
                type: emitResponse.responseTypes.FAIL,
                errorMessage: 'Error',
                message: 'Error occurred, please try again',
            };
        });
        return () => unsubscribe();
    }, [onCheckoutFail, emitResponse.responseTypes.FAIL]);


    useEffect(() => {
        const unsubscribe = onPaymentSetup(() => {
            let response = {
                type: emitResponse.responseTypes.SUCCESS, meta: {},
            };

            response.meta.paymentMethodData = {
                ...state,
                'isblocks': '1',
                'billing_country': billing.billingAddress.country,
                'billing_address_1': billing.billingAddress.address_1,
                'billing_address_2': billing.billingAddress.address_2,
            };

            return response;
        });
        return () => unsubscribe();
    }, [onPaymentSetup, emitResponse.responseTypes.SUCCESS, state]);



    const LazyComponent = React.lazy(() => {
        if (customTemplatePaymentMethodIds.includes(gateway.paymentMethodId)) {
           return import(`./gateways/${gateway.paymentMethodId}`);
        } else if (separateCreditCards.includes(gateway.paymentMethodId)) {
           return import(`./gateways/buckaroo_creditcard`);
        }

        return import('./gateways/default_payment')
    });

    return (
        <div className='container'>
            <span className='description'>{gateway.description}</span>
            <span className='descriptionError'>{errorMessage}</span>
            <PaymentContext.Provider value={{ state, updateFormState, updateMultiple, handleChange }}>
                <Suspense fallback={<div>Loading...</div>}>
                    <LazyComponent methodName={methodName} gateway={gateway} billing={billing.billingData}/>
                </Suspense>
            </PaymentContext.Provider>
        </div>
    );
}

const registerBuckarooPaymentMethods = ({wc, buckaroo_gateways}) => {
    const {registerPaymentMethod} = wc.wcBlocksRegistry;
    buckaroo_gateways.forEach((gateway) => {
        registerPaymentMethod(createOptions(gateway, BuckarooComponent));
    });
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