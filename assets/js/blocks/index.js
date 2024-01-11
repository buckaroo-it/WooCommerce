import React, { useState, useEffect } from 'react';
import DefaultPayment from "./gateways/default_payment";

const BuckarooComponent = ({ gateway, eventRegistration, emitResponse }) => {
    const [processingErrorMessage, setErrorMessage] = useState('');
    const [selectedIssuer, setSelectedIssuer] = useState('');
    const [dob, setDob] = useState(''); // If you need to handle date of birth
    const [PaymentComponent, setPaymentComponent] = useState(null);


    useEffect(() => {
        const unsubscribeProcessing = eventRegistration.onCheckoutFail(
            (props) => {
                setErrorMessage(props.processingResponse.paymentDetails.errorMessage);
                return {
                    type: emitResponse.responseTypes.FAIL,
                    errorMessage: 'Error',
                    message: 'Error occurred, please try again',
                };
            }
        );
        return () => unsubscribeProcessing();
    }, [eventRegistration, emitResponse]);

    useEffect(() => {
        const unsubscribe = eventRegistration.onPaymentSetup(() => {
            let response = {
                type: emitResponse.responseTypes.SUCCESS,
                meta: {},
            };

            let paymentMethodData = {
                'isblocks': '1',
                [gateway.paymentMethodId + '_issuer']: selectedIssuer,
                [gateway.paymentMethodId + '_birthdate']: dob
            };
            response.meta.paymentMethodData = paymentMethodData;
            return response;
        });
        return () => unsubscribe();
    }, [eventRegistration, emitResponse, selectedIssuer, dob, gateway.paymentMethodId]);

    useEffect(() => {
        import(`./gateways/${gateway.paymentMethodId}`)
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
        return <div>Loading...</div>;
    }

    return (
        <div className='PPMFWC_container'>
            <span className='description'>{gateway.description}</span>
            <span className='descriptionError'>{processingErrorMessage}</span>
            <PaymentComponent
                paymentName={gateway.title}
                idealIssuers={gateway.idealIssuers}
                payByBankIssuers={gateway.payByBankIssuers}
                payByBankSelectedIssuer={gateway.payByBankSelectedIssuer}
                displayMode={gateway.displayMode}
                buckarooImagesUrl={gateway.buckarooImagesUrl}
                onSelectIssuer={setSelectedIssuer}
                onBirthdateChange={(date) => setDob(date)}
            />
        </div>
    );
}

function BuckarooLabel({image_path, title})
{
    return React.createElement('div', {className: 'buckaroo_method_block'},
        title, React.createElement('img', {src: image_path, style: {float: 'right'}}, null));
}

const registerBuckarooPaymentMethods = ({wc, buckaroo_gateways}) => {
    const {registerPaymentMethod} = wc.wcBlocksRegistry;
    const {useEffect} = wp.element;
    buckaroo_gateways.forEach(

        (gateway) => {
            registerPaymentMethod(createOptions(gateway, BuckarooComponent, useEffect));
        }
    );
}

function decodeHtmlEntities(input) {
    var doc = new DOMParser().parseFromString(input, "text/html");
    return doc.documentElement.textContent;
}

const createOptions = (gateway, BuckarooComponent, useEffect) => {
    return {
        name: gateway.paymentMethodId,
        label: React.createElement(BuckarooLabel, {image_path: gateway.image_path, title: decodeHtmlEntities(gateway.title)}),
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