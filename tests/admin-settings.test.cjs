// Tests for library/js/admin-settings.js, run with `node --test tests/`.
// The script only needs a few DOM calls, so a small fake DOM is enough.
const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const SCRIPT = require.resolve('../library/js/admin-settings.js');
const SOURCE = fs.readFileSync(SCRIPT, 'utf8');

function element(props = {}) {
    const listeners = {};
    const node = {
        style: {},
        value: '',
        attributes: {},
        children: {},
        parent: null,
        addEventListener(type, handler) {
            (listeners[type] = listeners[type] || []).push(handler);
        },
        dispatch(type, event = {}) {
            (listeners[type] || []).forEach(handler => handler(event));
        },
        listenerCount(type) {
            return (listeners[type] || []).length;
        },
        closest(selector) {
            for (let node = this.parent; node; node = node.parent) {
                if (node.matches === selector) {
                    return node;
                }
            }
            return null;
        },
        getAttribute(name) {
            return this.attributes[name];
        },
        querySelector(selector) {
            return this.children[selector] || null;
        },
    };

    // Given properties win, so the fake document can bring its own lookups
    return Object.assign(node, props);
}

/**
 * Run the script against a fake page.
 * byId: elements for getElementById, bySelector: elements for document.querySelector
 */
function load({ settings, byId = {}, bySelector = {}, readyState = 'complete', href = 'https://shop.test/wp-admin/admin.php?page=wc-settings' } = {}) {
    const document = element({
        readyState,
        getElementById: id => byId[id] || null,
        querySelector: selector => bySelector[selector] || null,
    });
    const context = vm.createContext({ document, URL, location: { href } });
    context.window = context;
    if (settings !== undefined) {
        context.buckarooAdminSettings = settings;
    }
    vm.runInContext(SOURCE, context, { filename: SCRIPT });

    return { context, document };
}

const HOSTED = { select: 'method', clientId: 'client-id', clientSecret: 'client-secret' };

function hostedFieldsPage(method) {
    const select = element({ value: method });
    const idRow = element({ matches: 'tr' });
    const secretRow = element({ matches: 'tr' });
    const byId = {
        method: select,
        'client-id': element({ parent: idRow }),
        'client-secret': element({ parent: secretRow }),
    };

    return { select, idRow, secretRow, byId };
}

test('hosted fields rows do nothing without settings or a select', () => {
    assert.doesNotThrow(() => load());
    assert.doesNotThrow(() => load({ settings: {} }));
    assert.doesNotThrow(() => load({ settings: { hostedFields: HOSTED } }));
});

test('hosted fields credentials only show for the hosted fields method', () => {
    const page = hostedFieldsPage('redirect');
    load({ settings: { hostedFields: HOSTED }, byId: page.byId });

    assert.equal(page.idRow.style.display, 'none');
    assert.equal(page.secretRow.style.display, 'none');

    page.select.value = 'encrypt';
    page.select.dispatch('change');
    assert.equal(page.idRow.style.display, '');
    assert.equal(page.secretRow.style.display, '');

    page.select.value = 'redirect';
    page.select.dispatch('change');
    assert.equal(page.idRow.style.display, 'none');
});

test('hosted fields toggle skips fields that are missing or outside a row', () => {
    const select = element({ value: 'encrypt' });
    const loose = element();
    assert.doesNotThrow(() => load({ settings: { hostedFields: HOSTED }, byId: { method: select, 'client-id': loose } }));
    assert.deepEqual(loose.style, {});
});

function sectionMenu({ scrollWidth, clientWidth }) {
    const menu = element({ matches: 'ul.subsubsub', scrollWidth, clientWidth, offsetLeft: 10, scrollLeft: 0 });
    const item = element({ parent: menu, offsetLeft: 400, offsetWidth: 120 });
    const current = element({ parent: item });
    current.parentElement = item;

    return { menu, current };
}

const CURRENT_SECTION = 'ul.subsubsub a.current[href*="tab=buckaroo_settings"]';

test('section menu is left alone when there is no current section', () => {
    assert.doesNotThrow(() => load({ bySelector: {} }));
});

test('section menu is left alone when it fits', () => {
    const { menu, current } = sectionMenu({ scrollWidth: 300, clientWidth: 300 });
    load({ bySelector: { [CURRENT_SECTION]: current } });
    assert.equal(menu.scrollLeft, 0);
});

test('section menu scrolls the current section to the middle when it overflows', () => {
    const { menu, current } = sectionMenu({ scrollWidth: 700, clientWidth: 300 });
    load({ bySelector: { [CURRENT_SECTION]: current } });
    // item.offsetLeft - menu.offsetLeft - (menu.clientWidth - item.offsetWidth) / 2
    assert.equal(menu.scrollLeft, 400 - 10 - (300 - 120) / 2);
});

function logToolbar(value = '') {
    const input = element({ value });
    const button = element();
    const toolbar = element({
        attributes: { 'data-bk-logs-search': 'https://shop.test/wp-admin/admin.php?page=wc-settings&log_date=02-11-2026&s=old&errors=1' },
        children: { 'input[type="search"]': input, '.bk-logs-search .button': button },
    });
    const page = load({ bySelector: { '[data-bk-logs-search]': toolbar } });

    return { input, button, location: page.context.location };
}

function query(href) {
    return Object.fromEntries(new URL(href).searchParams);
}

test('log search does nothing without the toolbar', () => {
    const { context } = load({ bySelector: {} });
    assert.equal(context.location.href, 'https://shop.test/wp-admin/admin.php?page=wc-settings');
});

test('log search button opens the filtered day with the trimmed query', () => {
    const { input, button, location } = logToolbar('  order 123 ');
    button.dispatch('click');
    assert.deepEqual(query(location.href), { page: 'wc-settings', log_date: '02-11-2026', s: 'order 123', errors: '1' });
});

test('an empty log search removes the query', () => {
    const { button, location } = logToolbar('   ');
    button.dispatch('click');
    assert.deepEqual(query(location.href), { page: 'wc-settings', log_date: '02-11-2026', errors: '1' });
});

test('enter in the log search field searches instead of saving the settings form', () => {
    const { input, location } = logToolbar('refund');
    const start = location.href;

    let prevented = false;
    input.dispatch('keydown', { key: 'a', preventDefault: () => (prevented = true) });
    assert.equal(prevented, false);
    assert.equal(location.href, start);

    input.dispatch('keydown', { key: 'Enter', preventDefault: () => (prevented = true) });
    assert.equal(prevented, true);
    assert.equal(query(location.href).s, 'refund');
});

test('clearing the log search field with its x shows all entries again', () => {
    const { input, location } = logToolbar('refund');
    const start = location.href;

    input.dispatch('search');
    assert.equal(location.href, start);

    input.value = '';
    input.dispatch('search');
    assert.equal(query(location.href).s, undefined);
});

test('setup waits for the page when the script loads early', () => {
    const page = hostedFieldsPage('redirect');
    const { document } = load({ settings: { hostedFields: HOSTED }, byId: page.byId, readyState: 'loading' });

    assert.equal(page.idRow.style.display, undefined);
    assert.equal(document.listenerCount('DOMContentLoaded'), 1);

    document.dispatch('DOMContentLoaded');
    assert.equal(page.idRow.style.display, 'none');
});
