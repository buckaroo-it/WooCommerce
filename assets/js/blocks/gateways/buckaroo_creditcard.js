import React, { useEffect, useMemo, useRef } from 'react';
import { __ } from '@wordpress/i18n';
import useFormData from '../hooks/useFormData';
import DefaultDropdown from '../partials/buckaroo_creditcard_dropdown';

function CreditCard({
    eventRegistration,
    emitResponse,
    onStateChange,
    methodName,
    locale,
    setErrorMessage,
    gateway: { paymentMethodId, creditCardIssuers, creditCardMethod, creditCardIsSecure },
}) {
    const { onPaymentSetup } = eventRegistration;
    const sdkClientRef = useRef(null);
    const tokenExpiresAtRef = useRef(null);

    const { formState, handleChange, updateFormState } = useFormData(
        {
            [`${paymentMethodId}-encrypted-data`]: undefined,
            [`${paymentMethodId}-creditcard-issuer`]: undefined,
        },
        onStateChange
    );

    const isEncryptAndSecure = useMemo(
        () => creditCardMethod === 'encrypt' && creditCardIsSecure === true,
        [creditCardMethod, creditCardIsSecure]
    );

    // Map issuer names for supported services
    const mapIssuer = issuer => {
        const mapping = {
            amex: 'Amex',
            maestro: 'Maestro',
            mastercard: 'MasterCard',
            visa: 'Visa',
        };
        return mapping[issuer.servicename] || issuer.servicename;
    };

    // Schedule token refresh before expiry
    const scheduleTokenRefresh = expiresIn => {
        const refreshTime = Math.max(expiresIn * 1000 - 1000, 0);
        setTimeout(refreshHostedFields, refreshTime);
    };

    async function initializeHostedFields() {
        try {
            const now = Date.now();
            const response = await fetch('/?wc-api=WC_Gateway_Buckaroo_creditcard-hosted-fields-token', {
                method: 'GET',
                headers: { 'Content-Type': 'application/json' },
            });

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const data = await response.json();
            tokenExpiresAtRef.current = now + data.expires_in * 1000;
            scheduleTokenRefresh(data.expires_in);

            const sdkClient = new BuckarooHostedFieldsSdk.HFClient(data.access_token);
            sdkClient.setLanguage(locale);

            const services =
                paymentMethodId === 'buckaroo_creditcard'
                    ? creditCardIssuers
                    : [
                          {
                              servicename: paymentMethodId.replace('buckaroo_creditcard_', ''),
                          },
                      ];
            sdkClient.setSupportedServices(services.map(mapIssuer));

            sdkClientRef.current = sdkClient;

            await sdkClient.startSession(event => {
                sdkClient.handleValidation(
                    event,
                    'cc-name-error',
                    'cc-number-error',
                    'cc-expiry-error',
                    'cc-cvc-error'
                );
            });

            const mountingOperations = [
                {
                    mount: sdkClient.mountCardHolderName,
                    wrapper: '#cc-name-wrapper',
                    config: {
                        id: 'ccname',
                        placeHolder: 'John Doe',
                        labelSelector: '#cc-name-label',
                        baseStyling: {},
                    },
                },
                {
                    mount: sdkClient.mountCardNumber,
                    wrapper: '#cc-number-wrapper',
                    config: {
                        id: 'cc',
                        placeHolder: '555x xxxx xxxx xxxx',
                        labelSelector: '#cc-number-label',
                        baseStyling: {},
                    },
                },
                {
                    mount: sdkClient.mountCvc,
                    wrapper: '#cc-cvc-wrapper',
                    config: {
                        id: 'cvc',
                        placeHolder: '1234',
                        labelSelector: '#cc-cvc-label',
                        baseStyling: {},
                    },
                },
                {
                    mount: sdkClient.mountExpiryDate,
                    wrapper: '#cc-expiry-wrapper',
                    config: {
                        id: 'expiry',
                        placeHolder: 'MM / YY',
                        labelSelector: '#cc-expiry-label',
                        baseStyling: {},
                    },
                },
            ];

            for (const op of mountingOperations) {
                await op.mount(op.wrapper, op.config);
            }

            setTimeout(() => setErrorMessage(''), 3000);
        } catch (error) {
            console.error(error);
            setErrorMessage('This is error message');
        }
    }

    async function refreshHostedFields() {
        document
            .querySelectorAll(
                '#cc-name-wrapper iframe, #cc-number-wrapper iframe, #cc-expiry-wrapper iframe, #cc-cvc-wrapper iframe'
            )
            .forEach(el => el.remove());

        setErrorMessage(
            __('We are refreshing the payment form, because the session has expired.', 'wc-buckaroo-bpe-gateway')
        );

        await initializeHostedFields();
    }

    useEffect(() => {
        if (isEncryptAndSecure) {
            initializeHostedFields();
        } else if (paymentMethodId.includes('buckaroo_creditcard_')) {
            updateFormState(
                `${paymentMethodId}-creditcard-issuer`,
                paymentMethodId.replace('buckaroo_creditcard_', '')
            );
        }
    }, [methodName]);

    useEffect(() => {
        if (isEncryptAndSecure) {
            const unsubscribe = eventRegistration.onPaymentSetup(async () => {
                if (!sdkClientRef.current) {
                    return {
                        type: emitResponse.responseTypes.FAIL,
                        errorMessage: __('Failed to initialize Buckaroo hosted fields.', 'wc-buckaroo-bpe-gateway'),
                    };
                }

                if (Date.now() > tokenExpiresAtRef.current) {
                    await refreshHostedFields();
                    return {
                        type: emitResponse.responseTypes.FAIL,
                        errorMessage: __('Session expired, please try again.', 'wc-buckaroo-bpe-gateway'),
                    };
                }

                try {
                    const paymentToken = await sdkClientRef.current.submitSession();
                    if (!paymentToken) {
                        throw new Error('Failed to get encrypted card data.');
                    }

                    return {
                        type: emitResponse.responseTypes.SUCCESS,
                        meta: {
                            paymentMethodData: {
                                [`${paymentMethodId}-encrypted-data`]: paymentToken,
                                [`${paymentMethodId}-creditcard-issuer`]: sdkClientRef.current.getService(),
                            },
                        },
                    };
                } catch (error) {
                    console.error(error);
                    return {
                        type: emitResponse.responseTypes.FAIL,
                        errorMessage: __(error, 'wc-buckaroo-bpe-gateway'),
                    };
                }
            });
            return () => unsubscribe();
        }
    }, [onPaymentSetup, paymentMethodId]);

    return (
        <div>
            {creditCardMethod === 'redirect' && paymentMethodId === 'buckaroo_creditcard' && (
                <div className="form-row form-row-wide">
                    <DefaultDropdown
                        paymentMethodId={paymentMethodId}
                        creditCardIssuers={creditCardIssuers}
                        handleChange={handleChange}
                    />
                </div>
            )}
            {isEncryptAndSecure && (
                <div className="method--bankdata">
                    <div className="form-row">
                        <label id="cc-name-label" className="buckaroo-label" htmlFor={`${paymentMethodId}-cardname`}>
                            {__('Cardholder Name:', 'wc-buckaroo-bpe-gateway')}
                            <span className="required">*</span>
                        </label>
                        <div id="cc-name-wrapper" className="cardHolderName input-text" />
                        <div id="cc-name-error" className="input-error" />
                    </div>

                    <div className="form-row">
                        <label
                            id="cc-number-label"
                            className="buckaroo-label"
                            htmlFor={`${paymentMethodId}-cardnumber`}
                        >
                            {__('Card Number:', 'wc-buckaroo-bpe-gateway')}
                            <span className="required">*</span>
                        </label>
                        <div id="cc-number-wrapper" className="cardNumber input-text" />
                        <div id="cc-number-error" className="input-error" />
                    </div>

                    <div className="form-row form-row-first">
                        <label className="buckaroo-label" htmlFor={`${paymentMethodId}-cardmonth`} id="cc-expiry-label">
                            {__('Expiration Month:', 'wc-buckaroo-bpe-gateway')}
                            <span className="required">*</span>
                        </label>
                        <div id="cc-expiry-wrapper" className="expirationDate input-text" />
                        <div id="cc-expiry-error" className="input-error" />
                    </div>

                    <div className="form-row form-row-last">
                        <label id="cc-cvc-label" className="buckaroo-label" htmlFor={`${paymentMethodId}-cardcvc`}>
                            {__('CVC:', 'wc-buckaroo-bpe-gateway')}
                            <span className="required">*</span>
                        </label>
                        <div id="cc-cvc-wrapper" className="cvc input-text" />
                        <div id="cc-cvc-error" className="input-error" />
                    </div>

                    <div className="form-row form-row-wide validate-required"></div>
                    <div className="required" style={{ textAlign: 'right' }}>
                        *{__('Required', 'wc-buckaroo-bpe-gateway')}
                    </div>
                </div>
            )}
        </div>
    );
}

export default CreditCard;
