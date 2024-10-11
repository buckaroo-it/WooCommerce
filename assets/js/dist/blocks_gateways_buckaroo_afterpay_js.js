"use strict";
/*
 * ATTENTION: The "eval" devtool has been used (maybe by default in mode: "development").
 * This devtool is neither made for production nor for readable output files.
 * It uses "eval()" calls to create a separate source file in the browser devtools.
 * If you are trying to read the output file, select a different devtool (https://webpack.js.org/configuration/devtool/)
 * or disable the default devtool with "devtool: false".
 * If you are looking for production-ready output files, see mode: "production" (https://webpack.js.org/configuration/mode/).
 */
(self["webpackChunk"] = self["webpackChunk"] || []).push([["blocks_gateways_buckaroo_afterpay_js"],{

/***/ "./blocks/gateways/buckaroo_afterpay.js":
/*!**********************************************!*\
  !*** ./blocks/gateways/buckaroo_afterpay.js ***!
  \**********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

eval("__webpack_require__.r(__webpack_exports__);\n/* harmony export */ __webpack_require__.d(__webpack_exports__, {\n/* harmony export */   \"default\": () => (__WEBPACK_DEFAULT_EXPORT__)\n/* harmony export */ });\n/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ \"react\");\n/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);\n/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ \"@wordpress/i18n\");\n/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);\n/* harmony import */ var _partials_buckaroo_partial_birth_field__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../partials/buckaroo_partial_birth_field */ \"./blocks/partials/buckaroo_partial_birth_field.js\");\n/* harmony import */ var _partials_buckaroo_financial_warning__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../partials/buckaroo_financial_warning */ \"./blocks/partials/buckaroo_financial_warning.js\");\n/* harmony import */ var _partials_buckaroo_terms_and_condition__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../partials/buckaroo_terms_and_condition */ \"./blocks/partials/buckaroo_terms_and_condition.js\");\n/* harmony import */ var _partials_buckaroo_afterpay_b2b__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../partials/buckaroo_afterpay_b2b */ \"./blocks/partials/buckaroo_afterpay_b2b.js\");\n/* harmony import */ var _partials_buckaroo_phone__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../partials/buckaroo_phone */ \"./blocks/partials/buckaroo_phone.js\");\n/* harmony import */ var _hooks_useFormData__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ../hooks/useFormData */ \"./blocks/hooks/useFormData.js\");\n/* harmony import */ var _partials_buckaroo_coc_field__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ../partials/buckaroo_coc_field */ \"./blocks/partials/buckaroo_coc_field.js\");\nfunction _typeof(o) { \"@babel/helpers - typeof\"; return _typeof = \"function\" == typeof Symbol && \"symbol\" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && \"function\" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? \"symbol\" : typeof o; }, _typeof(o); }\nfunction _slicedToArray(r, e) { return _arrayWithHoles(r) || _iterableToArrayLimit(r, e) || _unsupportedIterableToArray(r, e) || _nonIterableRest(); }\nfunction _nonIterableRest() { throw new TypeError(\"Invalid attempt to destructure non-iterable instance.\\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.\"); }\nfunction _unsupportedIterableToArray(r, a) { if (r) { if (\"string\" == typeof r) return _arrayLikeToArray(r, a); var t = {}.toString.call(r).slice(8, -1); return \"Object\" === t && r.constructor && (t = r.constructor.name), \"Map\" === t || \"Set\" === t ? Array.from(r) : \"Arguments\" === t || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(t) ? _arrayLikeToArray(r, a) : void 0; } }\nfunction _arrayLikeToArray(r, a) { (null == a || a > r.length) && (a = r.length); for (var e = 0, n = Array(a); e < a; e++) n[e] = r[e]; return n; }\nfunction _iterableToArrayLimit(r, l) { var t = null == r ? null : \"undefined\" != typeof Symbol && r[Symbol.iterator] || r[\"@@iterator\"]; if (null != t) { var e, n, i, u, a = [], f = !0, o = !1; try { if (i = (t = t.call(r)).next, 0 === l) { if (Object(t) !== t) return; f = !1; } else for (; !(f = (e = i.call(t)).done) && (a.push(e.value), a.length !== l); f = !0); } catch (r) { o = !0, n = r; } finally { try { if (!f && null != t[\"return\"] && (u = t[\"return\"](), Object(u) !== u)) return; } finally { if (o) throw n; } } return a; } }\nfunction _arrayWithHoles(r) { if (Array.isArray(r)) return r; }\nfunction _defineProperty(e, r, t) { return (r = _toPropertyKey(r)) in e ? Object.defineProperty(e, r, { value: t, enumerable: !0, configurable: !0, writable: !0 }) : e[r] = t, e; }\nfunction _toPropertyKey(t) { var i = _toPrimitive(t, \"string\"); return \"symbol\" == _typeof(i) ? i : i + \"\"; }\nfunction _toPrimitive(t, r) { if (\"object\" != _typeof(t) || !t) return t; var e = t[Symbol.toPrimitive]; if (void 0 !== e) { var i = e.call(t, r || \"default\"); if (\"object\" != _typeof(i)) return i; throw new TypeError(\"@@toPrimitive must return a primitive value.\"); } return (\"string\" === r ? String : Number)(t); }\n\n\n\n\n\n\n\n\n\nfunction AfterPayView(_ref) {\n  var onStateChange = _ref.onStateChange,\n    methodName = _ref.methodName,\n    _ref$gateway = _ref.gateway,\n    type = _ref$gateway.type,\n    b2b = _ref$gateway.b2b,\n    billing = _ref.billing;\n  var initialState = _defineProperty(_defineProperty(_defineProperty(_defineProperty(_defineProperty(_defineProperty({}, \"\".concat(methodName, \"-phone\"), (billing === null || billing === void 0 ? void 0 : billing.phone) || ''), \"\".concat(methodName, \"-birthdate\"), ''), \"\".concat(methodName, \"-b2b\"), ''), \"\".concat(methodName, \"-company-coc-registration\"), ''), \"\".concat(methodName, \"-company-name\"), ''), \"\".concat(methodName, \"-accept\"), '');\n  var _useFormData = (0,_hooks_useFormData__WEBPACK_IMPORTED_MODULE_7__[\"default\"])(initialState, onStateChange),\n    formState = _useFormData.formState,\n    handleChange = _useFormData.handleChange,\n    updateFormState = _useFormData.updateFormState;\n  var handleTermsChange = function handleTermsChange(value) {\n    updateFormState(\"\".concat(methodName, \"-accept\"), value);\n  };\n  var handleBirthDayChange = function handleBirthDayChange(value) {\n    updateFormState(\"\".concat(methodName, \"-birthdate\"), value);\n  };\n  var handlePhoneChange = function handlePhoneChange(value) {\n    updateFormState(\"\".concat(methodName, \"-phone\"), value);\n  };\n  var _useState = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)(false),\n    _useState2 = _slicedToArray(_useState, 2),\n    isAdditionalCheckboxChecked = _useState2[0],\n    setIsAdditionalCheckboxChecked = _useState2[1];\n  var handleAdditionalCheckboxChange = function handleAdditionalCheckboxChange(isChecked) {\n    setIsAdditionalCheckboxChecked(isChecked);\n    updateFormState(\"\".concat(methodName, \"-b2b\"), isChecked ? 'ON' : 'OFF');\n  };\n  return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"div\", null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(_partials_buckaroo_phone__WEBPACK_IMPORTED_MODULE_6__[\"default\"], {\n    paymentMethod: methodName,\n    formState: formState,\n    handlePhoneChange: handlePhoneChange\n  }), type === 'afterpayacceptgiro' && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(_partials_buckaroo_coc_field__WEBPACK_IMPORTED_MODULE_8__[\"default\"], {\n    methodName: methodName,\n    handleChange: handleChange\n  }), !isAdditionalCheckboxChecked && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(_partials_buckaroo_partial_birth_field__WEBPACK_IMPORTED_MODULE_2__[\"default\"], {\n    paymentMethod: methodName,\n    handleBirthDayChange: handleBirthDayChange\n  }), b2b === 'enable' && type === 'afterpaydigiaccept' && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"div\", null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"div\", {\n    className: \"form-row form-row-wide validate-required\"\n  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"label\", {\n    htmlFor: \"buckaroo-afterpay-b2b\"\n  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Checkout for company', 'wc-buckaroo-bpe-gateway'), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"input\", {\n    id: \"buckaroo-afterpay-b2b\",\n    name: \"buckaroo-afterpay-b2b\",\n    type: \"checkbox\",\n    onChange: handleAdditionalCheckboxChange\n  }))), isAdditionalCheckboxChecked && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(_partials_buckaroo_afterpay_b2b__WEBPACK_IMPORTED_MODULE_5__[\"default\"], {\n    handleChange: handleChange\n  })), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(_partials_buckaroo_terms_and_condition__WEBPACK_IMPORTED_MODULE_4__[\"default\"], {\n    paymentMethod: methodName,\n    handleTermsChange: handleTermsChange,\n    billingData: billing,\n    b2b: b2b\n  }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(_partials_buckaroo_financial_warning__WEBPACK_IMPORTED_MODULE_3__[\"default\"], {\n    paymentMethod: methodName\n  }));\n}\n/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (AfterPayView);\n\n//# sourceURL=webpack:///./blocks/gateways/buckaroo_afterpay.js?");

/***/ }),

/***/ "./blocks/partials/buckaroo_afterpay_b2b.js":
/*!**************************************************!*\
  !*** ./blocks/partials/buckaroo_afterpay_b2b.js ***!
  \**************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

eval("__webpack_require__.r(__webpack_exports__);\n/* harmony export */ __webpack_require__.d(__webpack_exports__, {\n/* harmony export */   \"default\": () => (__WEBPACK_DEFAULT_EXPORT__)\n/* harmony export */ });\n/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ \"react\");\n/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);\n/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ \"@wordpress/i18n\");\n/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);\n\n\nfunction AfterPayB2B(_ref) {\n  var handleChange = _ref.handleChange;\n  return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"span\", {\n    id: \"showB2BBuckaroo\"\n  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"p\", {\n    className: \"form-row form-row-wide validate-required\"\n  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Fill required fields if bill in on the company:', 'wc-buckaroo-bpe-gateway')), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"p\", {\n    className: \"form-row form-row-wide validate-required\"\n  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"label\", {\n    htmlFor: \"buckaroo-afterpay-company-coc-registration\"\n  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('COC (KvK) number:', 'wc-buckaroo-bpe-gateway'), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"span\", {\n    className: \"required\"\n  }, \"*\")), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"input\", {\n    id: \"buckaroo-afterpay-company-coc-registration\",\n    name: \"buckaroo-afterpay-company-coc-registration\",\n    className: \"input-text\",\n    type: \"text\",\n    maxLength: \"250\",\n    autoComplete: \"off\",\n    onChange: handleChange\n  })), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"p\", {\n    className: \"form-row form-row-wide validate-required\"\n  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"label\", {\n    htmlFor: \"buckaroo-afterpay-company-name\"\n  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Name of the organization:', 'wc-buckaroo-bpe-gateway'), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"span\", {\n    className: \"required\"\n  }, \"*\")), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"input\", {\n    id: \"buckaroo-afterpay-company-name\",\n    name: \"buckaroo-afterpay-company-name\",\n    className: \"input-text\",\n    type: \"text\",\n    maxLength: \"250\",\n    autoComplete: \"off\",\n    onChange: handleChange\n  })));\n}\n/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (AfterPayB2B);\n\n//# sourceURL=webpack:///./blocks/partials/buckaroo_afterpay_b2b.js?");

/***/ }),

/***/ "./blocks/partials/buckaroo_phone.js":
/*!*******************************************!*\
  !*** ./blocks/partials/buckaroo_phone.js ***!
  \*******************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

eval("__webpack_require__.r(__webpack_exports__);\n/* harmony export */ __webpack_require__.d(__webpack_exports__, {\n/* harmony export */   \"default\": () => (__WEBPACK_DEFAULT_EXPORT__)\n/* harmony export */ });\n/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ \"react\");\n/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);\n/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ \"@wordpress/i18n\");\n/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);\n\n\nfunction PhoneDropdown(_ref) {\n  var paymentMethod = _ref.paymentMethod,\n    formState = _ref.formState,\n    handlePhoneChange = _ref.handlePhoneChange;\n  var handleChange = function handleChange(e) {\n    var value = e.target.value;\n    handlePhoneChange(value);\n  };\n  return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"div\", {\n    className: \"form-row validate-required\"\n  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"label\", {\n    htmlFor: \"\".concat(paymentMethod, \"-phone\")\n  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Phone Number:', 'wc-buckaroo-bpe-gateway'), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"span\", {\n    className: \"required\"\n  }, \"*\")), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"input\", {\n    id: \"\".concat(paymentMethod, \"-phone\"),\n    name: \"\".concat(paymentMethod, \"-phone\"),\n    className: \"input-text\",\n    type: \"tel\",\n    autoComplete: \"off\",\n    value: formState[\"\".concat(paymentMethod, \"-phone\")] || '',\n    onChange: handleChange\n  }));\n}\n/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (PhoneDropdown);\n\n//# sourceURL=webpack:///./blocks/partials/buckaroo_phone.js?");

/***/ })

}]);