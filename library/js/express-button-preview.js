/**
 * Live express button preview on the gateway settings screen. The buttons are
 * inert: nothing is payable here.
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
                // Re-rendering over a live instance fails, so close it first.
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
                        // wp_localize_script passes strings; PayPal needs a number.
                        height: parseInt(config.height, 10),
                        color: valueOf('color', 'gold'),
                        shape: valueOf('shape', 'FALSE') === 'TRUE' ? 'pill' : 'rect',
                        label: valueOf('type', 'paypal'),
                    },
                });

                this.instance = buttons;

                // render() is asynchronous, so failures arrive through its promise.
                var rendered = buttons.render(container);
                if (rendered && typeof rendered.catch === 'function') {
                    rendered.catch(unavailable);
                }
            },
        },

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

        applepay: {
            src: function () {
                return config.appleSdk;
            },
            // Not ApplePaySession: the web component renders in any browser.
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

                // The component hides itself where Apple Pay is unavailable.
                // This is only a style preview, so remove those flags.
                var flags = ['hidden', 'aria-hidden', 'disabled'];
                var reveal = function () {
                    flags.forEach(function (name) {
                        button.removeAttribute(name);
                    });
                };

                reveal();

                // The flags are set asynchronously, after its availability check.
                if (window.MutationObserver) {
                    var observer = new window.MutationObserver(reveal);
                    observer.observe(button, { attributes: true, attributeFilter: flags });

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

        // Wallets size the button from the container; keep it at field width.
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
