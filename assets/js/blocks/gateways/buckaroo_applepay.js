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
 *   1. On mount, build a checkout-mode Apple Pay instance (no button).
 *   2. Intercept the Place Order button click (capture phase = still a user
 *      gesture, required by Safari) and open the Apple Pay sheet.
 *   3. On authorisation, keep the token and let Place Order proceed.
 *   4. onPaymentSetup hands the token to the server, which charges it against
 *      the order built from the checkout-form addresses.
 */
function BuckarooApplepayCheckout({ gateway, eventRegistration, emitResponse, setErrorMessage }) {
    const tokenRef = useRef(null);
    const applepayRef = useRef(null);

    const getPlaceOrderButton = () =>
        document.querySelector('.wc-block-components-checkout-place-order-button') ||
        document.querySelector('button.wc-block-components-button[type="submit"]');

    // Build the (button-less) Apple Pay instance for this method.
    useEffect(() => {
        if (!window.BuckarooApplePay || typeof window.BuckarooApplePay.create !== 'function') {
            return undefined;
        }

        try {
            applepayRef.current = window.BuckarooApplePay.create({
                isOnCheckout: true,
                renderButton: false,
                containerSelector: '.applepay-blocks-checkout-method',
                onAuthorized: payment => {
                    // Authorised: keep the token and let Place Order proceed.
                    tokenRef.current = JSON.stringify(payment);
                    const button = getPlaceOrderButton();
                    if (button) {
                        button.click();
                    }
                },
            });
            applepayRef.current.rebuild();
            applepayRef.current.init();
        } catch (e) {
            // Apple Pay unavailable in this context; method stays inert.
        }

        return () => {
            applepayRef.current = null;
        };
    }, []);

    // Intercept Place Order: open the Apple Pay sheet within the click gesture.
    // While this method's content is mounted it is the active payment method, so
    // the listener is only live for Apple Pay.
    useEffect(() => {
        const button = getPlaceOrderButton();
        if (!button) {
            return undefined;
        }

        const handler = event => {
            // Already authorised -> let WooCommerce place the order.
            if (tokenRef.current) {
                return;
            }
            if (!applepayRef.current) {
                return;
            }
            event.preventDefault();
            event.stopImmediatePropagation();
            applepayRef.current.triggerPayment(event);
        };

        button.addEventListener('click', handler, true);
        return () => button.removeEventListener('click', handler, true);
    }, []);

    // Provide the authorised token to the server during order placement.
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
