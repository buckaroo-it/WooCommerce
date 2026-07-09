import React, { useEffect, useRef } from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Apple Pay as a standard (selectable) Blocks checkout payment method.
 *
 * No Apple Pay button is rendered here (that is only for Express Checkout).
 * The customer selects Apple Pay and clicks the normal "Place Order" button;
 * that click opens the Apple Pay sheet (authorise only). Billing and shipping
 * come from the WooCommerce checkout form, not from Apple Pay.
 *
 * Flow:
 *   1. On mount, build a checkout-mode Apple Pay instance (no button). The
 *      applepay bundle has no script-dependency link to this bundle (that
 *      coupling breaks Blocks hydration), so window.BuckarooApplePay may not
 *      exist yet on mount — creation is lazy and retried.
 *   2. Intercept the Place Order click (document capture phase = still a user
 *      gesture, required by Apple) and open the Apple Pay sheet.
 *   3. On authorisation, keep the token and let Place Order proceed.
 *   4. The token is supplied to the server BOTH via the parent
 *      BuckarooComponent's onStateChange state (merged into paymentMethodData
 *      by its own onPaymentSetup observer) and via this component's
 *      onPaymentSetup. Blocks aborts the payment-setup event at the first
 *      observer that returns a response, so the token must survive either
 *      registration order.
 */

const PLACE_ORDER_SELECTOR =
    '.wc-block-components-checkout-place-order-button, ' +
    '.wc-block-checkout__actions button[type="submit"], ' +
    '.wc-block-checkout__actions_row button[type="submit"]';

function BuckarooApplepayCheckout({ gateway, eventRegistration, emitResponse, setErrorMessage, onStateChange }) {
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

    // Create the (button-less) Apple Pay instance if it does not exist yet.
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
                    // Authorised: keep the token and let Place Order proceed.
                    tokenRef.current = JSON.stringify(payment);
                    showError('');

                    // Hand the token to the parent BuckarooComponent, whose own
                    // onPaymentSetup observer merges this state into
                    // paymentMethodData — so the token reaches the server even
                    // when that observer runs (and short-circuits) first.
                    if (typeof onStateChangeRef.current === 'function') {
                        onStateChangeRef.current({ paymentData: tokenRef.current });
                    }

                    const button = getPlaceOrderButton();
                    if (button) {
                        button.click();
                    }
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

    // Build the instance on mount; retry briefly while the applepay bundle
    // finishes loading (script order between the bundles is not guaranteed).
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

    // Intercept Place Order: open the Apple Pay sheet within the click gesture.
    // Capture-phase listener on the document, so a re-rendered/replaced button
    // is still caught. While this content is mounted, Apple Pay is the active
    // payment method, so the listener is only live for Apple Pay.
    useEffect(() => {
        const handler = event => {
            const target = event.target;
            const button = target && typeof target.closest === 'function' ? target.closest(PLACE_ORDER_SELECTOR) : null;
            if (!button) {
                return;
            }

            // Already authorised -> let WooCommerce place the order.
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

    // Provide the authorised token to the server during order placement (used
    // when this observer happens to run before the parent's generic one).
    useEffect(() => {
        if (!eventRegistration || !eventRegistration.onPaymentSetup) {
            return undefined;
        }

        const unsubscribe = eventRegistration.onPaymentSetup(() => {
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
                    },
                },
            };
        });

        return () => unsubscribe();
    }, [eventRegistration, emitResponse]);

    return (
        <div className="payment_box payment_method_buckaroo buckaroo-applepay-checkout-method applepay-blocks-checkout-method" />
    );
}

export default BuckarooApplepayCheckout;
