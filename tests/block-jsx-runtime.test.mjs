import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { jsx, jsxs, Fragment } from '../assets/js/shared/jsx-runtime.js';

// React is installed with the block checkout build in assets/js.
const { createElement, Fragment: ReactFragment } = createRequire(new URL('../assets/js/package.json', import.meta.url))(
    'react'
);

const shape = element => ({ type: element.type, key: element.key, ref: element.ref, props: element.props });

test('jsx creates the same element as createElement', () => {
    const ref = () => {};
    assert.deepEqual(
        shape(jsx('div', { className: 'a', ref, children: 'text' }, 'k')),
        shape(createElement('div', { className: 'a', ref, key: 'k' }, 'text'))
    );
});

test('jsx without a key leaves the key empty', () => {
    assert.equal(jsx('span', { children: 'x' }).key, null);
    assert.equal(jsx('span', { children: 'x' }, undefined).key, null);
});

test('jsxs keeps the children array', () => {
    const children = [jsx('b', {}, '1'), jsx('i', {}, '2')];
    const element = jsxs('p', { children });
    assert.deepEqual(element.props.children, children);
});

test('Fragment is the React Fragment', () => {
    assert.equal(Fragment, ReactFragment);
});
