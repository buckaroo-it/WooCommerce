// Stand-in for `react/jsx-runtime` for bundled packages that use the automatic JSX
// runtime. It builds elements with the React that WordPress provides, so the bundle
// does not carry its own copy of the runtime and works with every React version
// WordPress ships. Wired up through `resolve.alias` in webpack.config.js.
import { createElement, Fragment } from 'react';

export function jsx(type, props, key) {
    return createElement(type, key === undefined ? props : { ...props, key });
}

export { jsx as jsxs, Fragment };
