import React, { useState, useEffect } from 'react';

import DefaultPayment from "./default_payment";
const BuckarooComponent = (props) =>{
    let {useEffect, gateway} = props
    const [ selectedIssuer, selectIssuer ] = wp.element.useState('');
    const [ processingErrorMessage, setErrorMessage ] = wp.element.useState('');
    const [ dob ] = wp.element.useState('');
    const { eventRegistration, emitResponse } = props;
    const {onPaymentSetup, onCheckoutValidation, onCheckoutFail} = eventRegistration;
    const [PaymentComponent, setPaymentComponent] = useState(null);


    useEffect(() => {
        const unsubscribddeProcessing = onCheckoutFail(
            (props) => {
                setErrorMessage(props.processingResponse.paymentDetails.errorMessage);
                return {
                    type: emitResponse.responseTypes.FAIL,
                    errorMessage: 'Error',
                    message: 'Error occurred, please try again',
                };
            }
        );
        return () => {
            unsubscribddeProcessing()
        };
    }, [onCheckoutFail]);

    useEffect(() => {
        const unsubscribeCheckoutValidation = onCheckoutValidation(
            () => {
                // if (gateway.showVatField == true && gateway.vatRequired == true && !vatNumber.length) {
                //     return {
                //         type: emitResponse.responseTypes.SUCCESS,
                //         errorMessage: gateway.texts.requiredVatNumber
                //     };
                // } else if (gateway.showCocField == true && gateway.cocRequired == true && !cocNumber.length) {
                //     return {
                //         type: emitResponse.responseTypes.SUCCESS,
                //         errorMessage: gateway.texts.requiredCocNumber
                //     };
                // }
                //
                // if (gateway.showbirthdate == true && gateway.birthdateRequired == true && !dob.length) {
                //     return {
                //         type: emitResponse.responseTypes.SUCCESS,
                //         errorMessage: gateway.texts.dobRequired
                //     };
                // }
            }
        );
        return () => {
            unsubscribeCheckoutValidation()
        };
    }, [onCheckoutValidation, dob]);

    useEffect(() => {
        const unsubscribe = onPaymentSetup(() => {
            let response = {
                type: emitResponse.responseTypes.SUCCESS,
                meta: {},
            };
            let paymentMethodData = {
                'isblocks': '1',
                'buckaroo-ideal-issuer': selectedIssuer, // Updated with the state
                [gateway.paymentMethodId + '_birthdate']: dob
            };
            response.meta.paymentMethodData = paymentMethodData;
            return response;
        });
        return () => {
            unsubscribe();
        };
    }, [onPaymentSetup, selectedIssuer, dob]);

    useEffect(() => {
        import(`./${gateway.paymentMethodId}`)
            .then(({ default: LoadedComponent }) => {
                setPaymentComponent(() => LoadedComponent);
            })
            .catch(error => {
                if (/Cannot find module/.test(error.message)) {
                    setPaymentComponent(() => DefaultPayment);
                } else {
                    console.error(`Error importing payment method module './${gateway.paymentMethodId}':`, error);
                    throw error;
                }
            });
    }, [gateway.paymentMethodId]);

    if (!PaymentComponent) {
        return <div>Loading...</div>; // Or some other placeholder
    }

    return React.createElement('div', {className: 'PPMFWC_container'},
        React.createElement('span', {className: 'description'}, gateway.description),
        React.createElement('span', {className: 'descriptionError'}, processingErrorMessage),

        React.createElement('div', {},
            <PaymentComponent paymentName={gateway.title} issuers={gateway.issuers} onSelectIssuer={props.onSelectIssuer} />
        ))

}

function BuckarooLabel({image_path, title})
{
    return React.createElement('div', {className: 'buckaroo_method_block'},
        title, React.createElement('img', {src: image_path, style: {float: 'right'}}, null));
}

const registerBuckarooPaymentMethods = ({wc, buckaroo_gateways}) => {
    console.log(buckaroo_gateways)
    const {registerPaymentMethod} = wc.wcBlocksRegistry;
    const {useEffect} = wp.element;
    buckaroo_gateways.forEach(

        (gateway) => {
            registerPaymentMethod(createOptions(gateway, BuckarooComponent, useEffect));
        }
    );
}

const createOptions = (gateway, BuckarooComponent, useEffect) => {
    return {
        name: gateway.paymentMethodId,
        label: React.createElement(BuckarooLabel, {image_path: gateway.image_path, title: gateway.title}),
        paymentMethodId: gateway.paymentMethodId,
        edit: React.createElement('div', null, ''),
        canMakePayment: ({cartTotals, billingData}) => {
            return true
        },
        ariaLabel: gateway.title,
        content: React.createElement(BuckarooComponent, {gateway: gateway, image_path: gateway.image_path, useEffect: useEffect})
    }
}

registerBuckarooPaymentMethods(window)