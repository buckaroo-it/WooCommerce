import React, {useEffect, useState} from 'react';
import DefaultPayment from "./gateways/default_payment";
import {convertUnderScoreToDash, decodeHtmlEntities} from './utils';
import {BuckarooLabel} from "./components/BuckarooLabel";

const BuckarooComponent = ({billing, gateway, eventRegistration, emitResponse}) => {
    const [processingErrorMessage, setErrorMessage] = useState('');
    const [selectedIssuer, setSelectedIssuer] = useState('');
    const [dob, setDob] = useState('');
    const [selectedGender, setSelectedGender] = useState('');
    const [termsAndConditions, setTermsAndConditions] = useState('Off');
    const [accountName, setAccountName] = useState('');
    const [iban, setIban] = useState('');
    const [bic, setBic] = useState('');
    const [lastName, setLastName] = useState('');
    const [firstName, setFirstName] = useState('');
    const [email, setEmail] = useState('');
    const [creditCard, setCreditCard] = useState('');
    const [PaymentComponent, setPaymentComponent] = useState(null);
    const [cocNumber, setCocNumber] = useState('');
    const [companyName, setCompanyName] = useState('');
    const methodName = convertUnderScoreToDash(gateway.paymentMethodId);

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

            response.meta.paymentMethodData = {
                'isblocks': '1',
                [`${methodName}-company-coc-registration`]: cocNumber,
                [`${methodName}-company-name`]: companyName,
                [`${methodName}-issuer`]: selectedIssuer,
                [`${methodName}-birthdate`]: dob,
                [`${methodName}-accept`]: termsAndConditions,
                [`${methodName}-gender`]: selectedGender,
                [`${methodName}-iban`]: iban,
                [`${methodName}-accountname`]: accountName,
                [`${methodName}-bic`]: bic,
                [`${methodName}-firstname`]: firstName,
                [`${methodName}-lastname`]: lastName,
                [`${methodName}-email`]: email,
                [`${methodName}-b2b`]: 'ON'
            };
            return response;
        });
        return () => unsubscribe();
    }, [eventRegistration, emitResponse, selectedIssuer, selectedGender, dob, gateway.paymentMethodId]);

    useEffect(() => {
        import(`./gateways/${gateway.paymentMethodId}`)
            .then(({default: LoadedComponent}) => {
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
        <div className='container'>
            <span className='description'>{gateway.description}</span>
            <span className='descriptionError'>{processingErrorMessage}</span>
            <PaymentComponent
                paymentName={gateway.paymentMethodId}
                idealIssuers={gateway.idealIssuers}
                payByBankIssuers={gateway.payByBankIssuers}
                payByBankSelectedIssuer={'2'}
                billingData={billing.billingAddress}
                displayMode={gateway.displayMode}
                buckarooImagesUrl={gateway.buckarooImagesUrl}
                genders={gateway.genders}
                creditCardIssuers={gateway.creditCardIssuers}
                b2b={gateway.b2b}
                onSelectCc={setCreditCard}
                onSelectIssuer={setSelectedIssuer}
                onSelectGender={(gender) => setSelectedGender(gender)}
                onBirthdateChange={(date) => setDob(date)}
                onCheckboxChange={(check) => setTermsAndConditions(check)}
                onAccountName={(accountName) => setAccountName(accountName)}
                onIbanChange={(iban) => setIban(iban)}
                onBicChange={(bic) => setBic(bic)}
                onFirstNameChange={(firstName) => setFirstName(firstName)}
                onLastNameChange={(lastName) => setLastName(lastName)}
                onEmailChange={(email) => setEmail(email)}
                onCocInput={(cocNumber) => setCocNumber(cocNumber)}
                onCompanyInput={(companyName) => setCompanyName(companyName)}
            />
        </div>
    );
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