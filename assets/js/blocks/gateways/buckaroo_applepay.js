import React, { useEffect, useRef } from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Apple Pay as a standard (selectable) Blocks checkout payment method.
 *
 * No Apple Pay button is rendered here (that is only for Express Checkout).
 * The customer selects Apple Pay and clicks the normal "Place Order" button;
 * that click opens the Apple Pay sheet (authorise only). Billing and shipping
 * come from the WooCommerce checkout form, not from Apple Pay.
 */

const PLACE_ORDER_SELECTOR =
    '.wc-block-components-checkout-place-order-button, ' +
    '.wc-block-checkout__actions button[type="submit"], ' +
    '.wc-block-checkout__actions_row button[type="submit"]';

// Temporary diagnostic logging (see applepay bundle) to trace the token
// through the Blocks checkout while the empty-paymentData issue is verified.
function bkLog(stage, details) {
    try {
        // eslint-disable-next-line no-console
        console.log(`[Buckaroo ApplePay][blocks:${stage}]`, details);
    } catch (e) {
        // never break checkout because of logging
    }
}

function BuckarooApplepayCheckout({ gateway, eventRegistration, emitResponse, setErrorMessage, onStateChange, billing }) {
    const tokenRef = useRef(null);
    const applepayRef = useRef(null);
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
        if (applepayRef.current) {
            return applepayRef.current;
        }
        if (!window.BuckarooApplePay || typeof window.BuckarooApplePay.create !== 'function') {
            return null;
        }

        try {
            const instance = window.BuckarooApplePay.create({
                isOnCheckout: true,
                renderButton: false,
                containerSelector: '.applepay-blocks-checkout-method',
                onAuthorized: payment => {
                    // `payment` is already normalised to a plain object by the
                    // applepay bundle (normalizeApplePayPayment), so stringify
                    // is safe here on every device.
                    tokenRef.current = JSON.stringify(payment);
                    bkLog('onAuthorized', {
                        tokenLength: tokenRef.current.length,
                        hasToken: !!(payment && payment.token),
                    });
                    showError('');

                    if (typeof onStateChangeRef.current === 'function') {
                        onStateChangeRef.current({ paymentData: tokenRef.current });
                    }

                    setTimeout(() => {
                        const button = getPlaceOrderButton();
                        bkLog('onAuthorized', button ? 'Clicking Place Order' : 'Place Order button not found');
                        if (button) {
                            button.click();
                        }
                    }, 0);
                },
            });
            instance.rebuild();
            instance.init();
            applepayRef.current = instance;
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
            applepayRef.current = null;
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
            if (!instance || instance.triggerPayment(event) !== true) {
                showError(
                    __(
                        'Apple Pay is not available in this browser or context. Please choose another payment method.',
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

        // IMPORTANT: priority 20 — this observer must run AFTER the generic
        // BuckarooComponent onPaymentSetup observer (default priority 10).
        // WooCommerce Blocks keeps only the LAST success response's
        // paymentMethodData ("the last observer response always wins"), so
        // when this ran first (old priority 5) the generic response REPLACED
        // it and the Apple Pay token never reached the server — the Buckaroo
        // API then received an empty PaymentData. The generic fields are
        // merged here so nothing is lost by winning.
        const unsubscribe = eventRegistration.onPaymentSetup(() => {
            bkLog('onPaymentSetup', {
                hasToken: !!tokenRef.current,
                tokenLength: tokenRef.current ? tokenRef.current.length : 0,
            });
            if (!tokenRef.current) {
                return {
                    type: emitResponse.responseTypes.ERROR,
                    message: __('Apple Pay authorisation was not completed.', 'wc-buckaroo-bpe-gateway'),
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
        <div className="payment_box payment_method_buckaroo buckaroo-applepay-checkout-method applepay-blocks-checkout-method" />
    );
}

export default BuckarooApplepayCheckout;
