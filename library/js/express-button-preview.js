/**
 * Render a live express button on the settings screen so the merchant sees the
 * choices above it before saving.
 *
 * Each wallet draws its own button, so this branches on the method the gateway
 * declared. Nothing is payable here: the buttons are inert previews.
 */
(function () {
    'use strict';

    var config = typeof buckarooExpressPreview !== 'undefined' ? buckarooExpressPreview : null;

    if (!config) {
        return;
    }

    var container = document.getElementById(config.containerId);

    if (!container) {
        return;
    }

    function field(name) {
        return config.fields[name] ? document.getElementById(config.fields[name]) : null;
    }

    function valueOf(name, fallback) {
        var el = field(name);

        return el && el.value !== '' ? el.value : fallback;
    }

    function message(text, reason) {
        container.textContent = text;
        if (window.console && reason) {
            console.warn('Buckaroo express preview:', reason);
        }
    }

    function unavailable(reason) {
        message(config.i18n.unavailable, reason);
    }

    function loadScript(src, onLoad) {
        var script = document.createElement('script');
        script.src = src;
        script.addEventListener('load', onLoad);
        script.addEventListener('error', function () {
            unavailable('failed to load ' + src);
        });
        document.getElementsByTagName('head')[0].appendChild(script);
    }

    var renderers = {
        /**
         * PayPal draws through its own SDK, which needs a client id merely to
         * load. Style keys match what paypal_express.js injects on the storefront.
         */
        paypal: {
            src: function () {
                return 'https://www.paypal.com/sdk/js?client-id=' + encodeURIComponent(config.clientId);
            },
            ready: function () {
                return window.paypal && typeof window.paypal.Buttons === 'function';
            },
            draw: function () {
                var buttons = window.paypal.Buttons({
                    onInit: function (data, actions) {
                        actions.disable();
                    },
                    style: {
                        // wp_localize_script stringifies every value and PayPal
                        // rejects a style.height that is not a number.
                        height: parseInt(config.height, 10),
                        color: valueOf('color', 'gold'),
                        shape: valueOf('shape', 'FALSE') === 'TRUE' ? 'pill' : 'rect',
                    },
                });

                // render() resolves asynchronously, so a rejection never reaches
                // the caller's try/catch.
                var rendered = buttons.render(container);
                if (rendered && typeof rendered.catch === 'function') {
                    rendered.catch(unavailable);
                }
            },
        },

        /**
         * Google builds a button element through its PaymentsClient. TEST
         * environment: the preview never talks to a real merchant account.
         */
        googlepay: {
            src: function () {
                return 'https://pay.google.com/gp/p/js/pay.js';
            },
            ready: function () {
                return window.google && window.google.payments && window.google.payments.api;
            },
            draw: function () {
                var client = new window.google.payments.api.PaymentsClient({ environment: 'TEST' });

                container.appendChild(
                    client.createButton({
                        buttonColor: valueOf('color', 'black') === 'white' ? 'white' : 'black',
                        buttonType: valueOf('label', 'pay'),
                        buttonSizeMode: 'fill',
                        onClick: function () {},
                    })
                );
            },
        },

        /**
         * Apple's button is a web component that only upgrades in Safari on
         * Apple hardware, so elsewhere the merchant is told why it is blank
         * rather than shown an empty box.
         */
        applepay: {
            src: function () {
                return config.appleSdk;
            },
            ready: function () {
                return typeof window.ApplePaySession !== 'undefined';
            },
            draw: function () {
                var button = document.createElement('apple-pay-button');
                button.setAttribute('buttonstyle', valueOf('color', 'black'));
                button.setAttribute('type', valueOf('label', 'plain'));
                button.setAttribute('locale', config.locale);
                button.style.width = '100%';
                button.style.setProperty('--apple-pay-button-height', config.height + 'px');
                container.appendChild(button);
            },
            unsupported: function () {
                message(config.i18n.appleOnly);
            },
        },
    };

    var renderer = renderers[config.method];

    if (!renderer) {
        return;
    }

    function render() {
        container.textContent = '';

        // Every wallet sizes its button from the container, and PayPal also
        // picks its height from the container width. Set inline so a cached
        // stylesheet or a more specific admin rule cannot widen the preview
        // into something the customer never sees.
        container.style.maxWidth = parseInt(config.previewWidth, 10) + 'px';

        if (!renderer.ready()) {
            if (renderer.unsupported) {
                renderer.unsupported();
            } else {
                unavailable(config.method + ' SDK unavailable');
            }

            return;
        }

        try {
            renderer.draw();
        } catch (error) {
            unavailable(error);
        }
    }

    Object.keys(config.fields).forEach(function (name) {
        var el = field(name);
        if (el) {
            el.addEventListener('change', render);
        }
    });

    if (renderer.ready()) {
        render();
    } else {
        loadScript(renderer.src(), render);
    }
})();
