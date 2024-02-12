import React, {useEffect, useState} from 'react';
import DefaultPayment from "./gateways/default_payment";
import {convertUnderScoreToDash, decodeHtmlEntities} from './utils/utils';
import {BuckarooLabel} from "./components/BuckarooLabel";

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
    const [selectedIssuer, setSelectedIssuer] = useState('');
    const [dob, setDob] = useState('');
    const [selectedGender, setSelectedGender] = useState('');
    const [termsAndConditions, setTermsAndConditions] = useState(null);
    const [accountName, setAccountName] = useState('');
    const [iban, setIban] = useState('');
    const [bic, setBic] = useState('');
    const [lastName, setLastName] = useState('');
    const [firstName, setFirstName] = useState('');
    const [phoneNumber, setPhoneNumber] = useState('');
    const [cardName, setCardNameChange] = useState('');
    const [cardNumber, setCardNumberChange] = useState('');
    const [cardMonth, setCardMonthChange] = useState('');
    const [cardYear, setCardYearChange] = useState('');
    const [cardCVC, setCardCVCChange] = useState('');
    const [email, setEmail] = useState('');
    const [creditCard, setCreditCard] = useState('');
    const [PaymentComponent, setPaymentComponent] = useState(null);
    const [cocNumber, setCocNumber] = useState('');
    const [cocRegistration, setCocRegistrationChange] = useState('');
    const [encryptedData, setEncryptedDataChange] = useState('');
    const [identificationNumber, setIdentificationNumber] = useState('');
    const [companyName, setCompanyName] = useState('');
    const [additionalCheckboxChange, setAdditionalCheckboxChange] = useState('');
    const methodName = convertUnderScoreToDash(gateway.paymentMethodId);

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
        const filterFalsyValues = (obj) => Object.entries(obj).reduce((acc, [key, value]) => {
            if (value != null && value !== '') {
                acc[key] = value;
            }
            return acc;
        }, {});


        const unsubscribe = eventRegistration.onPaymentSetup(() => {
            let response = {
                type: emitResponse.responseTypes.SUCCESS, meta: {},
            };

            response.meta.paymentMethodData = filterFalsyValues({
                'isblocks': '1',
                [`billing_country`]: billing.billingAddress.country,
                [`${methodName}-company-coc-registration`]: cocNumber,
                [`${methodName}-company-name`]: companyName,
                [`${methodName}-issuer`]: selectedIssuer,
                [`${methodName}-birthdate`]: dob,
                [`${methodName}-accept`]: termsAndConditions,
                [`${methodName}-gender`]: selectedGender,
                [`${methodName}-iban`]: iban,
                [`${methodName}-accountname`]: accountName,
                [`${methodName}-bic`]: bic,
                [`${methodName}-identification-number`]: identificationNumber,
                [`${methodName}-phone`]: phoneNumber,
                [`${methodName}-firstname`]: firstName,
                [`${methodName}-lastname`]: lastName,
                [`${methodName}-email`]: email,
                [`${methodName}-company-coc-registration`]: cocRegistration,
                [`${methodName}-b2b`]: additionalCheckboxChange ? 'ON' : 'OFF',
            });
            if (`${methodName}`.includes("buckaroo-creditcard")) {
                response.meta.paymentMethodData[`${gateway.paymentMethodId}-creditcard-issuer`] = creditCard;
                response.meta.paymentMethodData[`${gateway.paymentMethodId}-cardname`] = cardName;
                response.meta.paymentMethodData[`${gateway.paymentMethodId}-cardnumber`] = cardNumber;
                response.meta.paymentMethodData[`${gateway.paymentMethodId}-cardmonth`] = cardMonth;
                response.meta.paymentMethodData[`${gateway.paymentMethodId}-cardyear`] = cardYear;
                response.meta.paymentMethodData[`${gateway.paymentMethodId}-cardcvc`] = cardCVC;
                response.meta.paymentMethodData[`${gateway.paymentMethodId}-encrypted-data`] = encryptedData;
            }

            return response;
        });
        return () => unsubscribe();
    }, [eventRegistration, emitResponse, selectedIssuer, selectedGender, dob, gateway.paymentMethodId]);

    useEffect(() => {
        const loadPaymentComponent = async (methodId) => {
            try {
                let LoadedComponent = DefaultPayment;
                if (customTemplatePaymentMethodIds.includes(methodId)) {
                    ({default: LoadedComponent} = await import(`./gateways/${methodId}`));
                } else if (separateCreditCards.includes(methodId)) {
                    ({default: LoadedComponent} = await import(`./gateways/buckaroo_separate_credit_card`));
                }

                setPaymentComponent(() => LoadedComponent);
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
    const paymentConfig = {
        paymentInfo: {
            paymentName: gateway.paymentMethodId,
            idealIssuers: gateway.idealIssuers,
            payByBankIssuers: gateway.payByBankIssuers,
            payByBankSelectedIssuer: gateway.payByBankSelectedIssuer
        },
        billingData: billing.billingAddress,
        displayMode: gateway.displayMode,
        buckarooImagesUrl: gateway.buckarooImagesUrl,
        genders: gateway.genders,
        creditCardIssuers: gateway.creditCardIssuers,
        creditCardIsSecure: gateway.creditCardIsSecure,
        creditCardMethod: gateway.creditCardMethod,
        b2b: gateway.b2b,
        type: gateway.type,
        customer_type: gateway.customer_type,
    };
    const paymentCallbacks = {
        onSelectCc: setCreditCard,
        onSelectIssuer: setSelectedIssuer,
        onSelectGender: setSelectedGender,
        onBirthdateChange: setDob,
        onCheckboxChange: setTermsAndConditions,
        onAccountName: setAccountName,
        onIbanChange: setIban,
        onBicChange: setBic,
        onFirstNameChange: setFirstName,
        onPhoneNumberChange: setPhoneNumber,
        onLastNameChange: setLastName,
        onCardNameChange: setCardNameChange,
        onCardNumberChange: setCardNumberChange,
        onCardMonthChange: setCardMonthChange,
        onCardYearChange: setCardYearChange,
        onCardCVCChange: setCardCVCChange,
        onEmailChange: setEmail,
        onCocInput: setCocNumber,
        onCocRegistrationChange: setCocRegistrationChange,
        onEncryptedDataChange: setEncryptedDataChange,
        onIdentificationNumber: setIdentificationNumber,
        onCompanyInput: setCompanyName,
        onAdditionalCheckboxChange: setAdditionalCheckboxChange,
    };
    return (
        <div className='container'>
            <span className='description'>{gateway.description}</span>
            <span className='descriptionError'>{errorMessage}</span>
            <PaymentComponent config={paymentConfig} callbacks={paymentCallbacks}/>
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