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
                var url = 'https://www.paypal.com/sdk/js?client-id=' + encodeURIComponent(config.clientId);

                Object.keys(config.sdkParams || {}).forEach(function (name) {
                    url += '&' + name + '=' + encodeURIComponent(config.sdkParams[name]);
                });

                return url;
            },
            ready: function () {
                return window.paypal && typeof window.paypal.Buttons === 'function';
            },
            draw: function () {
                // Re-rendering into a container that still holds a live zoid
                // instance fails, so retire the previous one first.
                if (this.instance && typeof this.instance.close === 'function') {
                    try {
                        this.instance.close();
                    } catch (error) {
                        // Already gone.
                    }
                }

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
                        label: valueOf('type', 'paypal'),
                    },
                });

                // render() resolves asynchronously, so a rejection never reaches
                // the caller's try/catch.
                this.instance = buttons;

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
                        buttonType: valueOf('type', 'pay'),
                        buttonSizeMode: 'fill',
                        onClick: function () {},
                    })
                );
            },
        },

        /**
         * Apple's button is a web component, so the preview draws in any
         * browser; only a real payment needs Safari on Apple hardware.
         */
        applepay: {
            src: function () {
                return config.appleSdk;
            },
            /**
             * The button is a web component, so readiness means the element
             * is registered. Checking ApplePaySession instead would skip the
             * load in Safari and suppress the preview everywhere else.
             */
            ready: function () {
                return !!(window.customElements && window.customElements.get('apple-pay-button'));
            },
            draw: function () {
                var button = document.createElement('apple-pay-button');
                button.setAttribute('buttonstyle', valueOf('color', 'black'));
                button.setAttribute('type', valueOf('type', 'plain'));
                button.setAttribute('locale', config.locale);
                button.style.width = '100%';
                button.style.setProperty('--apple-pay-button-height', config.height + 'px');
                container.appendChild(button);

                // Once connected, the component flags itself hidden wherever
                // Apple Pay cannot actually be used, via
                //   :host([aria-hidden]), :host([hidden]) { display: none }
                // in its shadow root. The drawn button underneath is correct,
                // and this is a style preview rather than a payable button, so
                // clear the flags. hidden is a boolean attribute, so it has to
                // be removed: setting it to "false" still hides.
                var flags = ['hidden', 'aria-hidden', 'disabled'];
                var reveal = function () {
                    flags.forEach(function (name) {
                        button.removeAttribute(name);
                    });
                };

                reveal();

                // The flags are applied asynchronously, once the component has
                // finished its availability check, so watch for them rather
                // than guessing a delay. It sets them once and does not restore
                // them after removal, so this settles rather than looping.
                if (window.MutationObserver) {
                    var observer = new window.MutationObserver(reveal);
                    observer.observe(button, { attributes: true, attributeFilter: flags });

                    // The check is long done by then; stop watching so a
                    // settings page does not keep an observer alive forever.
                    window.setTimeout(function () {
                        observer.disconnect();
                    }, 10000);
                }
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
