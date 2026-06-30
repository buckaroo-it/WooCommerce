import React, { useEffect, useRef } from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Apple Pay as a standard (selectable) Blocks checkout payment method.
 *
 * Unlike the Express Checkout button, the Apple Pay sheet here only authorises
 * the payment — billing and shipping come from the WooCommerce checkout form.
 *
 * Flow:
 *   1. Render Apple's official <apple-pay-button> (via the shared applepay bundle).
 *   2. The customer clicks it -> the Buckaroo SDK opens the Apple Pay sheet
 *      within the user gesture (required by Safari).
 *   3. On authorisation we keep the token and trigger the Blocks "Place Order".
 *   4. onPaymentSetup hands the token to the server, which charges it and uses
 *      the addresses already entered in the checkout form.
 */
function BuckarooApplepayCheckout({ gateway, eventRegistration, emitResponse, setErrorMessage }) {
    const tokenRef = useRef(null);
    const applepayRef = useRef(null);

    const placeOrder = () => {
        const button =
            document.querySelector('.wc-block-components-checkout-place-order-button') ||
            document.querySelector('button.wc-block-components-button[type="submit"]');
        if (button) {
            button.click();
        }
    };

    useEffect(() => {
        if (!window.BuckarooApplePay || typeof window.BuckarooApplePay.create !== 'function') {
            return;
        }

        try {
            const applepay = window.BuckarooApplePay.create({
                isOnCheckout: true,
                buttonStyle: gateway.buttonStyle || 'black',
                containerSelector: '.applepay-checkout-button-container',
                onAuthorized: payment => {
                    tokenRef.current = JSON.stringify(payment);
                    placeOrder();
                },
            });

            applepayRef.current = applepay;
            applepay.rebuild();
            applepay.init();
        } catch (e) {
            // Apple Pay unavailable in this context; leave the method inert.
        }

        return () => {
            applepayRef.current = null;
        };
    }, [gateway.buttonStyle]);

    useEffect(() => {
        if (!eventRegistration || !eventRegistration.onPaymentSetup) {
            return;
        }

        const unsubscribe = eventRegistration.onPaymentSetup(() => {
            if (!tokenRef.current) {
                if (typeof setErrorMessage === 'function') {
                    setErrorMessage(
                        __('Please authorise the payment with the Apple Pay button first.', 'wc-buckaroo-bpe-gateway')
                    );
                }
                return {
                    type: emitResponse.responseTypes.ERROR,
                    message: __(
                        'Please authorise the payment with the Apple Pay button first.',
                        'wc-buckaroo-bpe-gateway'
                    ),
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
        <div className="payment_box payment_method_buckaroo buckaroo-applepay-checkout-method">
            <div className="applepay-checkout-button-container" />
            <p className="buckaroo-applepay-checkout-hint">
                {__(
                    'Click the Apple Pay button to authorise your payment, then your order will be placed.',
                    'wc-buckaroo-bpe-gateway'
                )}
            </p>
        </div>
    );
}

export default BuckarooApplepayCheckout;
