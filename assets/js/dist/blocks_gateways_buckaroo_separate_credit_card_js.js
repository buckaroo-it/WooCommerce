"use strict";
/*
 * ATTENTION: The "eval" devtool has been used (maybe by default in mode: "development").
 * This devtool is neither made for production nor for readable output files.
 * It uses "eval()" calls to create a separate source file in the browser devtools.
 * If you are trying to read the output file, select a different devtool (https://webpack.js.org/configuration/devtool/)
 * or disable the default devtool with "devtool: false".
 * If you are looking for production-ready output files, see mode: "production" (https://webpack.js.org/configuration/mode/).
 */
(self["webpackChunk"] = self["webpackChunk"] || []).push([["blocks_gateways_buckaroo_separate_credit_card_js"],{

/***/ "./blocks/gateways/buckaroo_separate_credit_card.js":
/*!**********************************************************!*\
  !*** ./blocks/gateways/buckaroo_separate_credit_card.js ***!
  \**********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

eval("__webpack_require__.r(__webpack_exports__);\n/* harmony export */ __webpack_require__.d(__webpack_exports__, {\n/* harmony export */   \"default\": () => (__WEBPACK_DEFAULT_EXPORT__)\n/* harmony export */ });\n/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ \"react\");\n/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);\n/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ \"@wordpress/i18n\");\n/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);\n/* harmony import */ var _services_BuckarooClientSideEncryption__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../services/BuckarooClientSideEncryption */ \"./blocks/services/BuckarooClientSideEncryption.js\");\n/* harmony import */ var _hooks_useFormData__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../hooks/useFormData */ \"./blocks/hooks/useFormData.js\");\nfunction _typeof(o) { \"@babel/helpers - typeof\"; return _typeof = \"function\" == typeof Symbol && \"symbol\" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && \"function\" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? \"symbol\" : typeof o; }, _typeof(o); }\nfunction _regeneratorRuntime() { \"use strict\"; /*! regenerator-runtime -- Copyright (c) 2014-present, Facebook, Inc. -- license (MIT): https://github.com/facebook/regenerator/blob/main/LICENSE */ _regeneratorRuntime = function _regeneratorRuntime() { return e; }; var t, e = {}, r = Object.prototype, n = r.hasOwnProperty, o = Object.defineProperty || function (t, e, r) { t[e] = r.value; }, i = \"function\" == typeof Symbol ? Symbol : {}, a = i.iterator || \"@@iterator\", c = i.asyncIterator || \"@@asyncIterator\", u = i.toStringTag || \"@@toStringTag\"; function define(t, e, r) { return Object.defineProperty(t, e, { value: r, enumerable: !0, configurable: !0, writable: !0 }), t[e]; } try { define({}, \"\"); } catch (t) { define = function define(t, e, r) { return t[e] = r; }; } function wrap(t, e, r, n) { var i = e && e.prototype instanceof Generator ? e : Generator, a = Object.create(i.prototype), c = new Context(n || []); return o(a, \"_invoke\", { value: makeInvokeMethod(t, r, c) }), a; } function tryCatch(t, e, r) { try { return { type: \"normal\", arg: t.call(e, r) }; } catch (t) { return { type: \"throw\", arg: t }; } } e.wrap = wrap; var h = \"suspendedStart\", l = \"suspendedYield\", f = \"executing\", s = \"completed\", y = {}; function Generator() {} function GeneratorFunction() {} function GeneratorFunctionPrototype() {} var p = {}; define(p, a, function () { return this; }); var d = Object.getPrototypeOf, v = d && d(d(values([]))); v && v !== r && n.call(v, a) && (p = v); var g = GeneratorFunctionPrototype.prototype = Generator.prototype = Object.create(p); function defineIteratorMethods(t) { [\"next\", \"throw\", \"return\"].forEach(function (e) { define(t, e, function (t) { return this._invoke(e, t); }); }); } function AsyncIterator(t, e) { function invoke(r, o, i, a) { var c = tryCatch(t[r], t, o); if (\"throw\" !== c.type) { var u = c.arg, h = u.value; return h && \"object\" == _typeof(h) && n.call(h, \"__await\") ? e.resolve(h.__await).then(function (t) { invoke(\"next\", t, i, a); }, function (t) { invoke(\"throw\", t, i, a); }) : e.resolve(h).then(function (t) { u.value = t, i(u); }, function (t) { return invoke(\"throw\", t, i, a); }); } a(c.arg); } var r; o(this, \"_invoke\", { value: function value(t, n) { function callInvokeWithMethodAndArg() { return new e(function (e, r) { invoke(t, n, e, r); }); } return r = r ? r.then(callInvokeWithMethodAndArg, callInvokeWithMethodAndArg) : callInvokeWithMethodAndArg(); } }); } function makeInvokeMethod(e, r, n) { var o = h; return function (i, a) { if (o === f) throw Error(\"Generator is already running\"); if (o === s) { if (\"throw\" === i) throw a; return { value: t, done: !0 }; } for (n.method = i, n.arg = a;;) { var c = n.delegate; if (c) { var u = maybeInvokeDelegate(c, n); if (u) { if (u === y) continue; return u; } } if (\"next\" === n.method) n.sent = n._sent = n.arg;else if (\"throw\" === n.method) { if (o === h) throw o = s, n.arg; n.dispatchException(n.arg); } else \"return\" === n.method && n.abrupt(\"return\", n.arg); o = f; var p = tryCatch(e, r, n); if (\"normal\" === p.type) { if (o = n.done ? s : l, p.arg === y) continue; return { value: p.arg, done: n.done }; } \"throw\" === p.type && (o = s, n.method = \"throw\", n.arg = p.arg); } }; } function maybeInvokeDelegate(e, r) { var n = r.method, o = e.iterator[n]; if (o === t) return r.delegate = null, \"throw\" === n && e.iterator[\"return\"] && (r.method = \"return\", r.arg = t, maybeInvokeDelegate(e, r), \"throw\" === r.method) || \"return\" !== n && (r.method = \"throw\", r.arg = new TypeError(\"The iterator does not provide a '\" + n + \"' method\")), y; var i = tryCatch(o, e.iterator, r.arg); if (\"throw\" === i.type) return r.method = \"throw\", r.arg = i.arg, r.delegate = null, y; var a = i.arg; return a ? a.done ? (r[e.resultName] = a.value, r.next = e.nextLoc, \"return\" !== r.method && (r.method = \"next\", r.arg = t), r.delegate = null, y) : a : (r.method = \"throw\", r.arg = new TypeError(\"iterator result is not an object\"), r.delegate = null, y); } function pushTryEntry(t) { var e = { tryLoc: t[0] }; 1 in t && (e.catchLoc = t[1]), 2 in t && (e.finallyLoc = t[2], e.afterLoc = t[3]), this.tryEntries.push(e); } function resetTryEntry(t) { var e = t.completion || {}; e.type = \"normal\", delete e.arg, t.completion = e; } function Context(t) { this.tryEntries = [{ tryLoc: \"root\" }], t.forEach(pushTryEntry, this), this.reset(!0); } function values(e) { if (e || \"\" === e) { var r = e[a]; if (r) return r.call(e); if (\"function\" == typeof e.next) return e; if (!isNaN(e.length)) { var o = -1, i = function next() { for (; ++o < e.length;) if (n.call(e, o)) return next.value = e[o], next.done = !1, next; return next.value = t, next.done = !0, next; }; return i.next = i; } } throw new TypeError(_typeof(e) + \" is not iterable\"); } return GeneratorFunction.prototype = GeneratorFunctionPrototype, o(g, \"constructor\", { value: GeneratorFunctionPrototype, configurable: !0 }), o(GeneratorFunctionPrototype, \"constructor\", { value: GeneratorFunction, configurable: !0 }), GeneratorFunction.displayName = define(GeneratorFunctionPrototype, u, \"GeneratorFunction\"), e.isGeneratorFunction = function (t) { var e = \"function\" == typeof t && t.constructor; return !!e && (e === GeneratorFunction || \"GeneratorFunction\" === (e.displayName || e.name)); }, e.mark = function (t) { return Object.setPrototypeOf ? Object.setPrototypeOf(t, GeneratorFunctionPrototype) : (t.__proto__ = GeneratorFunctionPrototype, define(t, u, \"GeneratorFunction\")), t.prototype = Object.create(g), t; }, e.awrap = function (t) { return { __await: t }; }, defineIteratorMethods(AsyncIterator.prototype), define(AsyncIterator.prototype, c, function () { return this; }), e.AsyncIterator = AsyncIterator, e.async = function (t, r, n, o, i) { void 0 === i && (i = Promise); var a = new AsyncIterator(wrap(t, r, n, o), i); return e.isGeneratorFunction(r) ? a : a.next().then(function (t) { return t.done ? t.value : a.next(); }); }, defineIteratorMethods(g), define(g, u, \"Generator\"), define(g, a, function () { return this; }), define(g, \"toString\", function () { return \"[object Generator]\"; }), e.keys = function (t) { var e = Object(t), r = []; for (var n in e) r.push(n); return r.reverse(), function next() { for (; r.length;) { var t = r.pop(); if (t in e) return next.value = t, next.done = !1, next; } return next.done = !0, next; }; }, e.values = values, Context.prototype = { constructor: Context, reset: function reset(e) { if (this.prev = 0, this.next = 0, this.sent = this._sent = t, this.done = !1, this.delegate = null, this.method = \"next\", this.arg = t, this.tryEntries.forEach(resetTryEntry), !e) for (var r in this) \"t\" === r.charAt(0) && n.call(this, r) && !isNaN(+r.slice(1)) && (this[r] = t); }, stop: function stop() { this.done = !0; var t = this.tryEntries[0].completion; if (\"throw\" === t.type) throw t.arg; return this.rval; }, dispatchException: function dispatchException(e) { if (this.done) throw e; var r = this; function handle(n, o) { return a.type = \"throw\", a.arg = e, r.next = n, o && (r.method = \"next\", r.arg = t), !!o; } for (var o = this.tryEntries.length - 1; o >= 0; --o) { var i = this.tryEntries[o], a = i.completion; if (\"root\" === i.tryLoc) return handle(\"end\"); if (i.tryLoc <= this.prev) { var c = n.call(i, \"catchLoc\"), u = n.call(i, \"finallyLoc\"); if (c && u) { if (this.prev < i.catchLoc) return handle(i.catchLoc, !0); if (this.prev < i.finallyLoc) return handle(i.finallyLoc); } else if (c) { if (this.prev < i.catchLoc) return handle(i.catchLoc, !0); } else { if (!u) throw Error(\"try statement without catch or finally\"); if (this.prev < i.finallyLoc) return handle(i.finallyLoc); } } } }, abrupt: function abrupt(t, e) { for (var r = this.tryEntries.length - 1; r >= 0; --r) { var o = this.tryEntries[r]; if (o.tryLoc <= this.prev && n.call(o, \"finallyLoc\") && this.prev < o.finallyLoc) { var i = o; break; } } i && (\"break\" === t || \"continue\" === t) && i.tryLoc <= e && e <= i.finallyLoc && (i = null); var a = i ? i.completion : {}; return a.type = t, a.arg = e, i ? (this.method = \"next\", this.next = i.finallyLoc, y) : this.complete(a); }, complete: function complete(t, e) { if (\"throw\" === t.type) throw t.arg; return \"break\" === t.type || \"continue\" === t.type ? this.next = t.arg : \"return\" === t.type ? (this.rval = this.arg = t.arg, this.method = \"return\", this.next = \"end\") : \"normal\" === t.type && e && (this.next = e), y; }, finish: function finish(t) { for (var e = this.tryEntries.length - 1; e >= 0; --e) { var r = this.tryEntries[e]; if (r.finallyLoc === t) return this.complete(r.completion, r.afterLoc), resetTryEntry(r), y; } }, \"catch\": function _catch(t) { for (var e = this.tryEntries.length - 1; e >= 0; --e) { var r = this.tryEntries[e]; if (r.tryLoc === t) { var n = r.completion; if (\"throw\" === n.type) { var o = n.arg; resetTryEntry(r); } return o; } } throw Error(\"illegal catch attempt\"); }, delegateYield: function delegateYield(e, r, n) { return this.delegate = { iterator: values(e), resultName: r, nextLoc: n }, \"next\" === this.method && (this.arg = t), y; } }, e; }\nfunction ownKeys(e, r) { var t = Object.keys(e); if (Object.getOwnPropertySymbols) { var o = Object.getOwnPropertySymbols(e); r && (o = o.filter(function (r) { return Object.getOwnPropertyDescriptor(e, r).enumerable; })), t.push.apply(t, o); } return t; }\nfunction _objectSpread(e) { for (var r = 1; r < arguments.length; r++) { var t = null != arguments[r] ? arguments[r] : {}; r % 2 ? ownKeys(Object(t), !0).forEach(function (r) { _defineProperty(e, r, t[r]); }) : Object.getOwnPropertyDescriptors ? Object.defineProperties(e, Object.getOwnPropertyDescriptors(t)) : ownKeys(Object(t)).forEach(function (r) { Object.defineProperty(e, r, Object.getOwnPropertyDescriptor(t, r)); }); } return e; }\nfunction asyncGeneratorStep(n, t, e, r, o, a, c) { try { var i = n[a](c), u = i.value; } catch (n) { return void e(n); } i.done ? t(u) : Promise.resolve(u).then(r, o); }\nfunction _asyncToGenerator(n) { return function () { var t = this, e = arguments; return new Promise(function (r, o) { var a = n.apply(t, e); function _next(n) { asyncGeneratorStep(a, r, o, _next, _throw, \"next\", n); } function _throw(n) { asyncGeneratorStep(a, r, o, _next, _throw, \"throw\", n); } _next(void 0); }); }; }\nfunction _defineProperty(e, r, t) { return (r = _toPropertyKey(r)) in e ? Object.defineProperty(e, r, { value: t, enumerable: !0, configurable: !0, writable: !0 }) : e[r] = t, e; }\nfunction _toPropertyKey(t) { var i = _toPrimitive(t, \"string\"); return \"symbol\" == _typeof(i) ? i : i + \"\"; }\nfunction _toPrimitive(t, r) { if (\"object\" != _typeof(t) || !t) return t; var e = t[Symbol.toPrimitive]; if (void 0 !== e) { var i = e.call(t, r || \"default\"); if (\"object\" != _typeof(i)) return i; throw new TypeError(\"@@toPrimitive must return a primitive value.\"); } return (\"string\" === r ? String : Number)(t); }\n\n\n\n\nfunction SeparateCreditCard(_ref) {\n  var onStateChange = _ref.onStateChange,\n    gateway = _ref.gateway;\n  var paymentMethodId = gateway.paymentMethodId,\n    creditCardMethod = gateway.creditCardMethod,\n    creditCardIsSecure = gateway.creditCardIsSecure;\n  var initialState = _defineProperty(_defineProperty(_defineProperty(_defineProperty(_defineProperty(_defineProperty(_defineProperty({}, \"\".concat(paymentMethodId, \"-creditcard-issuer\"), paymentMethodId.replace('buckaroo_creditcard_', '')), \"\".concat(paymentMethodId, \"-cardname\"), ''), \"\".concat(paymentMethodId, \"-cardnumber\"), ''), \"\".concat(paymentMethodId, \"-cardmonth\"), ''), \"\".concat(paymentMethodId, \"-cardyear\"), ''), \"\".concat(paymentMethodId, \"-cardcvc\"), ''), \"\".concat(paymentMethodId, \"-encrypted-data\"), '');\n  // Destructure the object returned by useFormData\n  var _useFormData = (0,_hooks_useFormData__WEBPACK_IMPORTED_MODULE_3__[\"default\"])(initialState, onStateChange),\n    formState = _useFormData.formState,\n    handleChange = _useFormData.handleChange,\n    updateFormState = _useFormData.updateFormState;\n  (0,react__WEBPACK_IMPORTED_MODULE_0__.useEffect)(function () {\n    updateFormState(\"\".concat(paymentMethodId, \"-creditcard-issuer\"), initialState[\"\".concat(paymentMethodId, \"-creditcard-issuer\")]);\n  }, [\"\".concat(paymentMethodId, \"-creditcard-issuer\")]);\n  var handleEncryption = /*#__PURE__*/function () {\n    var _ref2 = _asyncToGenerator(/*#__PURE__*/_regeneratorRuntime().mark(function _callee() {\n      var encryptedData;\n      return _regeneratorRuntime().wrap(function _callee$(_context) {\n        while (1) switch (_context.prev = _context.next) {\n          case 0:\n            if (!(creditCardMethod !== 'encrypt' || !creditCardIsSecure)) {\n              _context.next = 2;\n              break;\n            }\n            return _context.abrupt(\"return\");\n          case 2:\n            _context.prev = 2;\n            _context.next = 5;\n            return (0,_services_BuckarooClientSideEncryption__WEBPACK_IMPORTED_MODULE_2__[\"default\"])({\n              cardName: formState[\"\".concat(paymentMethodId, \"-cardname\")],\n              cardNumber: formState[\"\".concat(paymentMethodId, \"-cardnumber\")],\n              cardMonth: formState[\"\".concat(paymentMethodId, \"-cardmonth\")],\n              cardYear: formState[\"\".concat(paymentMethodId, \"-cardyear\")],\n              cardCVC: formState[\"\".concat(paymentMethodId, \"-cardcvc\")]\n            });\n          case 5:\n            encryptedData = _context.sent;\n            onStateChange(_objectSpread(_objectSpread({}, formState), {}, _defineProperty({}, \"\".concat(paymentMethodId, \"-encrypted-data\"), encryptedData)));\n            _context.next = 12;\n            break;\n          case 9:\n            _context.prev = 9;\n            _context.t0 = _context[\"catch\"](2);\n            console.error('Encryption error:', _context.t0);\n          case 12:\n          case \"end\":\n            return _context.stop();\n        }\n      }, _callee, null, [[2, 9]]);\n    }));\n    return function handleEncryption() {\n      return _ref2.apply(this, arguments);\n    };\n  }();\n  (0,react__WEBPACK_IMPORTED_MODULE_0__.useEffect)(function () {\n    handleEncryption();\n  }, [formState[\"\".concat(paymentMethodId, \"-cardname\")], formState[\"\".concat(paymentMethodId, \"-cardnumber\")], formState[\"\".concat(paymentMethodId, \"-cardmonth\")], formState[\"\".concat(paymentMethodId, \"-cardyear\")], formState[\"\".concat(paymentMethodId, \"-cardcvc\")]]);\n  return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"div\", null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"div\", {\n    className: \"method--bankdata\"\n  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"input\", {\n    type: \"hidden\",\n    name: \"\".concat(paymentMethodId, \"-creditcard-issuer\"),\n    id: \"\".concat(paymentMethodId, \"-creditcard-issuer\"),\n    className: \"cardHolderName input-text\",\n    value: formState[\"\".concat(paymentMethodId, \"-creditcard-issuer\")]\n  }), creditCardIsSecure === true && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"div\", null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"div\", {\n    className: \"form-row\"\n  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"label\", {\n    className: \"buckaroo-label\",\n    htmlFor: \"\".concat(paymentMethodId, \"-cardname\")\n  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Cardholder Name:', 'wc-buckaroo-bpe-gateway'), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"span\", {\n    className: \"required\"\n  }, \"*\")), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"input\", {\n    type: \"text\",\n    name: \"\".concat(paymentMethodId, \"-cardname\"),\n    id: \"\".concat(paymentMethodId, \"-cardname\"),\n    placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Cardholder Name:', 'wc-buckaroo-bpe-gateway'),\n    className: \"cardHolderName input-text\",\n    maxLength: \"250\",\n    autoComplete: \"off\",\n    onChange: handleChange\n  })), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"div\", {\n    className: \"form-row\"\n  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"label\", {\n    className: \"buckaroo-label\",\n    htmlFor: \"\".concat(paymentMethodId, \"-cardnumber\")\n  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Card Number:', 'wc-buckaroo-bpe-gateway'), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"span\", {\n    className: \"required\"\n  }, \"*\")), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"input\", {\n    type: \"text\",\n    name: \"\".concat(paymentMethodId, \"-cardnumber\"),\n    id: \"\".concat(paymentMethodId, \"-cardnumber\"),\n    placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Card Number:', 'wc-buckaroo-bpe-gateway'),\n    className: \"cardNumber input-text\",\n    maxLength: \"250\",\n    autoComplete: \"off\",\n    onChange: handleChange\n  })), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"div\", {\n    className: \"form-row\"\n  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"label\", {\n    className: \"buckaroo-label\",\n    htmlFor: \"\".concat(paymentMethodId, \"-cardmonth\")\n  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Expiration Month:', 'wc-buckaroo-bpe-gateway'), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"span\", {\n    className: \"required\"\n  }, \"*\")), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"input\", {\n    type: \"text\",\n    maxLength: \"2\",\n    name: \"\".concat(paymentMethodId, \"-cardmonth\"),\n    id: \"\".concat(paymentMethodId, \"-cardmonth\"),\n    placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Expiration Month:', 'wc-buckaroo-bpe-gateway'),\n    className: \"expirationMonth input-text\",\n    autoComplete: \"off\",\n    onChange: handleChange\n  })), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"div\", {\n    className: \"form-row\"\n  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"label\", {\n    className: \"buckaroo-label\",\n    htmlFor: \"\".concat(paymentMethodId, \"-cardyear\")\n  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Expiration Year:', 'wc-buckaroo-bpe-gateway'), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"span\", {\n    className: \"required\"\n  }, \"*\")), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"input\", {\n    type: \"text\",\n    maxLength: \"4\",\n    name: \"\".concat(paymentMethodId, \"-cardyear\"),\n    id: \"\".concat(paymentMethodId, \"-cardyear\"),\n    placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Expiration Year:', 'wc-buckaroo-bpe-gateway'),\n    className: \"expirationYear input-text\",\n    autoComplete: \"off\",\n    onChange: handleChange\n  })), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"div\", {\n    className: \"form-row\"\n  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"label\", {\n    className: \"buckaroo-label\",\n    htmlFor: \"\".concat(paymentMethodId, \"-cardcvc\")\n  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('CVC:', 'wc-buckaroo-bpe-gateway'), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"span\", {\n    className: \"required\"\n  }, \"*\")), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"input\", {\n    type: \"password\",\n    maxLength: \"4\",\n    name: \"\".concat(paymentMethodId, \"-cardcvc\"),\n    id: \"\".concat(paymentMethodId, \"-cardcvc\"),\n    placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('CVC:', 'wc-buckaroo-bpe-gateway'),\n    className: \"cvc input-text\",\n    autoComplete: \"off\",\n    onChange: handleChange\n  })), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"div\", {\n    className: \"form-row form-row-wide validate-required\"\n  }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"div\", {\n    className: \"required\",\n    style: {\n      \"float\": 'right'\n    }\n  }, \"*\", (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Required', 'wc-buckaroo-bpe-gateway')))));\n}\n/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (SeparateCreditCard);\n\n//# sourceURL=webpack:///./blocks/gateways/buckaroo_separate_credit_card.js?");

/***/ })

}]);