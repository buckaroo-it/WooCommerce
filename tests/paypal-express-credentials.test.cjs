const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

for (const [mode, isTest, expectedAlerts] of [
    ['Test', true, []],
    ['Live', false, ['Merchant ID is required']],
]) {
    test(`PayPal ${mode} initialization handles an empty merchant ID`, () => {
        const alerts = [];
        const context = vm.createContext({
            jQuery: () => ({ ready() {} }),
            document: {},
            alert: message => alerts.push(message),
            BuckarooSdk: { PayPal: {}, Base: { setTestMode() {} } },
            buckaroo_paypal_express: {
                websiteKey: 'website-key',
                merchant_id: null,
                is_test: isTest,
                page: 'cart',
                currency: 'EUR',
                ajaxurl: '/ajax',
                i18n: { merchant_id_required: 'Merchant ID is required' },
            },
        });
        const source = fs.readFileSync(require.resolve('../library/js/paypal_express.js'), 'utf8');
        vm.runInContext(source, context);
        vm.runInContext('BuckarooPaypalExpress.prototype.init = function () {}; BuckarooInitPaypalExpress();', context);
        assert.deepEqual(alerts, expectedAlerts);
    });
}
