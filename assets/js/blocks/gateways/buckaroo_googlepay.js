import React, { useEffect, useRef } from 'react';
import { __ } from '@wordpress/i18n';

const PLACE_ORDER_SELECTOR =
    '.wc-block-components-checkout-place-order-button, ' +
    '.wc-block-checkout__actions button[type="submit"], ' +
    '.wc-block-checkout__actions_row button[type="submit"]';

function BuckarooGooglepayCheckout({
    gateway,
    eventRegistration,
    emitResponse,
    setErrorMessage,
    onStateChange,
    billing,
}) {
    const tokenRef = useRef(null);
    const googlepayRef = useRef(null);
    const onStateChangeRef = useRef(onStateChange);
    onStateChangeRef.current = onStateChange;
    const setErrorMessageRef = useRef(setErrorMessage);
    setErrorMessageRef.current = setErrorMessage;

    const getPlaceOrderButton = () => document.querySelector(PLACE_ORDER_SELECTOR);

    const showError = message => {
        if (typeof setErrorMessageRef.current === 'function') {
            setErrorMessageRef.current(message);
        }
    };

    const ensureInstance = () => {
        if (googlepayRef.current) {
            return googlepayRef.current;
        }
        if (!window.BuckarooGooglePay || typeof window.BuckarooGooglePay.create !== 'function') {
            return null;
        }

        try {
            const instance = window.BuckarooGooglePay.create({
                isOnCheckout: true,
                containerSelector: '.googlepay-blocks-checkout-method',
                buttonElementId: 'googlepay-blocks-checkout-button-element',
                onAuthorized: payment => {
                    tokenRef.current = JSON.stringify(payment);
                    showError('');

                    if (typeof onStateChangeRef.current === 'function') {
                        onStateChangeRef.current({ paymentData: tokenRef.current });
                    }

                    setTimeout(() => {
                        const button = getPlaceOrderButton();
                        if (button) {
                            button.click();
                        }
                    }, 0);
                },
            });
            instance.rebuild();
            instance.init();
            googlepayRef.current = instance;
            return instance;
        } catch (e) {
            return null;
        }
    };

    useEffect(() => {
        if (ensureInstance()) {
            return undefined;
        }

        const timer = setInterval(() => {
            if (ensureInstance()) {
                clearInterval(timer);
            }
        }, 250);
        const stop = setTimeout(() => clearInterval(timer), 10000);

        return () => {
            clearInterval(timer);
            clearTimeout(stop);
            googlepayRef.current = null;
        };
    }, []);

    useEffect(() => {
        const handler = event => {
            const target = event.target;
            const button = target && typeof target.closest === 'function' ? target.closest(PLACE_ORDER_SELECTOR) : null;
            if (!button) {
                return;
            }

            if (tokenRef.current) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            const instance = ensureInstance();
            if (!instance || instance.triggerPayment() !== true) {
                showError(
                    __(
                        'Google Pay is not available in this browser or context. Please choose another payment method.',
                        'wc-buckaroo-bpe-gateway'
                    )
                );
            }
        };

        document.addEventListener('click', handler, true);
        return () => document.removeEventListener('click', handler, true);
    }, []);

    useEffect(() => {
        if (!eventRegistration || !eventRegistration.onPaymentSetup) {
            return undefined;
        }

        // Priority 20: must run AFTER the generic BuckarooComponent observer
        // (priority 10) because Blocks keeps only the last success response.
        const unsubscribe = eventRegistration.onPaymentSetup(() => {
            if (!tokenRef.current) {
                return {
                    type: emitResponse.responseTypes.ERROR,
                    message: __('Google Pay authorisation was not completed.', 'wc-buckaroo-bpe-gateway'),
                };
            }

            return {
                type: emitResponse.responseTypes.SUCCESS,
                meta: {
                    paymentMethodData: {
                        paymentData: tokenRef.current,
                        isblocks: '1',
                        billing_company: (billing && billing.company) || '',
                        billing_country: (billing && billing.country) || '',
                        billing_address_1: (billing && billing.address_1) || '',
                        billing_address_2: (billing && billing.address_2) || '',
                    },
                },
            };
        }, 20);

        return () => unsubscribe();
    }, [eventRegistration, emitResponse, billing]);

    return (
        <div className="payment_box payment_method_buckaroo buckaroo-googlepay-checkout-method googlepay-blocks-checkout-method" />
    );
}

export default BuckarooGooglepayCheckout;
