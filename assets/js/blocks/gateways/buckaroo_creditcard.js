import React, { useEffect, useMemo, useRef } from 'react';
import { __ } from '@wordpress/i18n';
import useFormData from '../hooks/useFormData';
import DefaultDropdown from '../partials/buckaroo_creditcard_dropdown';
import { hostedFieldStyling, syncHostedFieldErrors } from '../../shared/hosted-field-styling';

const HOSTED_FIELD_ERROR_IDS = ['cc-name-error', 'cc-number-error', 'cc-expiry-error', 'cc-cvc-error'];

// A hosted card field in the markup of the Checkout Block's text input, with its
// validation error inside it as WooCommerce renders it. The fields are siblings,
// like the Block's own inputs, so its spacing between consecutive inputs applies.
// The input only draws the field; the card iframe is mounted over it into the frame.
function HostedField({ name, label, className = '' }) {
    return (
        <div className={`wc-block-components-text-input is-active buckaroo-hosted-field ${className}`.trim()}>
            <input type="text" className="buckaroo-hosted-field__input" tabIndex={-1} aria-hidden="true" readOnly />
            <div id={`cc-${name}-wrapper`} className="buckaroo-hosted-field__frame" />
            <label id={`cc-${name}-label`}>{label}</label>
            <div
                id={`cc-${name}-error`}
                className="wc-block-components-validation-error buckaroo-hosted-field__error"
                role="alert"
            />
        </div>
    );
}

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
                sdkClient.handleValidation(event, ...HOSTED_FIELD_ERROR_IDS);
                syncHostedFieldErrors(HOSTED_FIELD_ERROR_IDS);
            });

            const mountingOperations = [
                {
                    mount: sdkClient.mountCardHolderName,
                    wrapper: '#cc-name-wrapper',
                    config: {
                        id: 'ccname',
                        placeHolder: 'John Doe',
                        labelSelector: '#cc-name-label',
                    },
                },
                {
                    mount: sdkClient.mountCardNumber,
                    wrapper: '#cc-number-wrapper',
                    config: {
                        id: 'cc',
                        placeHolder: '555x xxxx xxxx xxxx',
                        labelSelector: '#cc-number-label',
                    },
                },
                {
                    mount: sdkClient.mountCvc,
                    wrapper: '#cc-cvc-wrapper',
                    config: {
                        id: 'cvc',
                        placeHolder: '1234',
                        labelSelector: '#cc-cvc-label',
                    },
                },
                {
                    mount: sdkClient.mountExpiryDate,
                    wrapper: '#cc-expiry-wrapper',
                    config: {
                        id: 'expiry',
                        placeHolder: 'MM / YY',
                        labelSelector: '#cc-expiry-label',
                    },
                },
            ];

            for (const op of mountingOperations) {
                const { baseStyling, cardLogoStyling } = hostedFieldStyling(op.wrapper);
                const config = { ...op.config, baseStyling };
                if (op.mount === sdkClient.mountCardNumber) {
                    config.cardLogoStyling = cardLogoStyling;
                }
                await op.mount(op.wrapper, config);
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
                <DefaultDropdown
                    paymentMethodId={paymentMethodId}
                    creditCardIssuers={creditCardIssuers}
                    handleChange={handleChange}
                />
            )}
            {isEncryptAndSecure && (
                <div className="buckaroo-hosted-fields">
                    <HostedField name="name" label={__('Cardholder Name:', 'wc-buckaroo-bpe-gateway')} />
                    <HostedField name="number" label={__('Card Number:', 'wc-buckaroo-bpe-gateway')} />
                    <HostedField
                        name="expiry"
                        label={__('Expiration Month:', 'wc-buckaroo-bpe-gateway')}
                        className="buckaroo-hosted-field--half"
                    />
                    <HostedField
                        name="cvc"
                        label={__('CVC:', 'wc-buckaroo-bpe-gateway')}
                        className="buckaroo-hosted-field--half"
                    />
                </div>
            )}
        </div>
    );
}

export default CreditCard;
