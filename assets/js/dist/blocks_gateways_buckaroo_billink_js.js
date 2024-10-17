"use strict";
/*
 * ATTENTION: The "eval" devtool has been used (maybe by default in mode: "development").
 * This devtool is neither made for production nor for readable output files.
 * It uses "eval()" calls to create a separate source file in the browser devtools.
 * If you are trying to read the output file, select a different devtool (https://webpack.js.org/configuration/devtool/)
 * or disable the default devtool with "devtool: false".
 * If you are looking for production-ready output files, see mode: "production" (https://webpack.js.org/configuration/mode/).
 */
(self["webpackChunk"] = self["webpackChunk"] || []).push([["blocks_gateways_buckaroo_billink_js"],{

/***/ "./blocks/gateways/buckaroo_billink.js":
/*!*********************************************!*\
  !*** ./blocks/gateways/buckaroo_billink.js ***!
  \*********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

eval("__webpack_require__.r(__webpack_exports__);\n/* harmony export */ __webpack_require__.d(__webpack_exports__, {\n/* harmony export */   \"default\": () => (__WEBPACK_DEFAULT_EXPORT__)\n/* harmony export */ });\n/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ \"react\");\n/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);\n/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ \"@wordpress/i18n\");\n/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);\n/* harmony import */ var _partials_buckaroo_partial_birth_field__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../partials/buckaroo_partial_birth_field */ \"./blocks/partials/buckaroo_partial_birth_field.js\");\n/* harmony import */ var _partials_buckaroo_gender__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../partials/buckaroo_gender */ \"./blocks/partials/buckaroo_gender.js\");\n/* harmony import */ var _partials_buckaroo_financial_warning__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../partials/buckaroo_financial_warning */ \"./blocks/partials/buckaroo_financial_warning.js\");\n/* harmony import */ var _partials_buckaroo_terms_and_condition__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../partials/buckaroo_terms_and_condition */ \"./blocks/partials/buckaroo_terms_and_condition.js\");\n/* harmony import */ var _hooks_useFormData__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../hooks/useFormData */ \"./blocks/hooks/useFormData.js\");\n/* harmony import */ var _partials_buckaroo_coc_field__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ../partials/buckaroo_coc_field */ \"./blocks/partials/buckaroo_coc_field.js\");\nfunction _typeof(o) { \"@babel/helpers - typeof\"; return _typeof = \"function\" == typeof Symbol && \"symbol\" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && \"function\" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? \"symbol\" : typeof o; }, _typeof(o); }\nfunction _slicedToArray(r, e) { return _arrayWithHoles(r) || _iterableToArrayLimit(r, e) || _unsupportedIterableToArray(r, e) || _nonIterableRest(); }\nfunction _nonIterableRest() { throw new TypeError(\"Invalid attempt to destructure non-iterable instance.\\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.\"); }\nfunction _unsupportedIterableToArray(r, a) { if (r) { if (\"string\" == typeof r) return _arrayLikeToArray(r, a); var t = {}.toString.call(r).slice(8, -1); return \"Object\" === t && r.constructor && (t = r.constructor.name), \"Map\" === t || \"Set\" === t ? Array.from(r) : \"Arguments\" === t || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(t) ? _arrayLikeToArray(r, a) : void 0; } }\nfunction _arrayLikeToArray(r, a) { (null == a || a > r.length) && (a = r.length); for (var e = 0, n = Array(a); e < a; e++) n[e] = r[e]; return n; }\nfunction _iterableToArrayLimit(r, l) { var t = null == r ? null : \"undefined\" != typeof Symbol && r[Symbol.iterator] || r[\"@@iterator\"]; if (null != t) { var e, n, i, u, a = [], f = !0, o = !1; try { if (i = (t = t.call(r)).next, 0 === l) { if (Object(t) !== t) return; f = !1; } else for (; !(f = (e = i.call(t)).done) && (a.push(e.value), a.length !== l); f = !0); } catch (r) { o = !0, n = r; } finally { try { if (!f && null != t[\"return\"] && (u = t[\"return\"](), Object(u) !== u)) return; } finally { if (o) throw n; } } return a; } }\nfunction _arrayWithHoles(r) { if (Array.isArray(r)) return r; }\nfunction _defineProperty(e, r, t) { return (r = _toPropertyKey(r)) in e ? Object.defineProperty(e, r, { value: t, enumerable: !0, configurable: !0, writable: !0 }) : e[r] = t, e; }\nfunction _toPropertyKey(t) { var i = _toPrimitive(t, \"string\"); return \"symbol\" == _typeof(i) ? i : i + \"\"; }\nfunction _toPrimitive(t, r) { if (\"object\" != _typeof(t) || !t) return t; var e = t[Symbol.toPrimitive]; if (void 0 !== e) { var i = e.call(t, r || \"default\"); if (\"object\" != _typeof(i)) return i; throw new TypeError(\"@@toPrimitive must return a primitive value.\"); } return (\"string\" === r ? String : Number)(t); }\n\n\n\n\n\n\n\n\nfunction Billink(_ref) {\n  var onStateChange = _ref.onStateChange,\n    methodName = _ref.methodName,\n    _ref$gateway = _ref.gateway,\n    genders = _ref$gateway.genders,\n    b2b = _ref$gateway.b2b,\n    billing = _ref.billing;\n  var initialState = _defineProperty(_defineProperty(_defineProperty(_defineProperty(_defineProperty({}, \"\".concat(methodName, \"-company-coc-registration\"), ''), \"\".concat(methodName, \"-VatNumber\"), ''), \"\".concat(methodName, \"-gender\"), ''), \"\".concat(methodName, \"-birthdate\"), ''), \"\".concat(methodName, \"-b2b\"), '');\n  var _useFormData = (0,_hooks_useFormData__WEBPACK_IMPORTED_MODULE_6__[\"default\"])(initialState, onStateChange),\n    handleChange = _useFormData.handleChange,\n    updateFormState = _useFormData.updateFormState;\n  var _useState = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)((billing === null || billing === void 0 ? void 0 : billing.company) || ''),\n    _useState2 = _slicedToArray(_useState, 2),\n    company = _useState2[0],\n    setCompany = _useState2[1];\n  (0,react__WEBPACK_IMPORTED_MODULE_0__.useEffect)(function () {\n    setCompany((billing === null || billing === void 0 ? void 0 : billing.company) || '');\n  }, [billing === null || billing === void 0 ? void 0 : billing.company]);\n  var handleBirthDayChange = function handleBirthDayChange(value) {\n    updateFormState(\"\".concat(methodName, \"-birthdate\"), value);\n  };\n  var handleTermsChange = function handleTermsChange(value) {\n    updateFormState(\"\".concat(methodName, \"-accept\"), value);\n  };\n  return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"div\", null, company !== '' ? /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"div\", {\n    id: \"buckaroo_billink_b2b\"\n  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(_partials_buckaroo_coc_field__WEBPACK_IMPORTED_MODULE_7__[\"default\"], {\n    methodName: methodName,\n    handleChange: handleChange\n  }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"p\", {\n    className: \"form-row form-row-wide validate-required\"\n  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"label\", {\n    htmlFor: \"\".concat(methodName, \"-VatNumber\")\n  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('VAT-number:', 'wc-buckaroo-bpe-gateway'), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"span\", {\n    className: \"required\"\n  }, \"*\")), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"input\", {\n    id: \"\".concat(methodName, \"-VatNumber\"),\n    name: \"\".concat(methodName, \"-VatNumber\"),\n    className: \"input-text\",\n    type: \"text\",\n    maxLength: \"250\",\n    autoComplete: \"off\",\n    onChange: handleChange\n  }))) : /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"div\", {\n    id: \"buckaroo_billink_b2c\"\n  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(_partials_buckaroo_gender__WEBPACK_IMPORTED_MODULE_3__[\"default\"], {\n    paymentMethod: methodName,\n    genders: genders,\n    handleChange: handleChange\n  }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(_partials_buckaroo_partial_birth_field__WEBPACK_IMPORTED_MODULE_2__[\"default\"], {\n    paymentMethod: methodName,\n    handleBirthDayChange: handleBirthDayChange\n  })), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(_partials_buckaroo_terms_and_condition__WEBPACK_IMPORTED_MODULE_5__[\"default\"], {\n    paymentMethod: methodName,\n    handleTermsChange: handleTermsChange,\n    billingData: billing,\n    b2b: b2b\n  }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(_partials_buckaroo_financial_warning__WEBPACK_IMPORTED_MODULE_4__[\"default\"], {\n    paymentMethod: methodName\n  }));\n}\n/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (Billink);\n\n//# sourceURL=webpack:///./blocks/gateways/buckaroo_billink.js?");

/***/ }),

/***/ "./blocks/partials/buckaroo_gender.js":
/*!********************************************!*\
  !*** ./blocks/partials/buckaroo_gender.js ***!
  \********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

eval("__webpack_require__.r(__webpack_exports__);\n/* harmony export */ __webpack_require__.d(__webpack_exports__, {\n/* harmony export */   \"default\": () => (__WEBPACK_DEFAULT_EXPORT__)\n/* harmony export */ });\n/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ \"react\");\n/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);\n/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ \"@wordpress/i18n\");\n/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);\nfunction _slicedToArray(r, e) { return _arrayWithHoles(r) || _iterableToArrayLimit(r, e) || _unsupportedIterableToArray(r, e) || _nonIterableRest(); }\nfunction _nonIterableRest() { throw new TypeError(\"Invalid attempt to destructure non-iterable instance.\\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.\"); }\nfunction _unsupportedIterableToArray(r, a) { if (r) { if (\"string\" == typeof r) return _arrayLikeToArray(r, a); var t = {}.toString.call(r).slice(8, -1); return \"Object\" === t && r.constructor && (t = r.constructor.name), \"Map\" === t || \"Set\" === t ? Array.from(r) : \"Arguments\" === t || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(t) ? _arrayLikeToArray(r, a) : void 0; } }\nfunction _arrayLikeToArray(r, a) { (null == a || a > r.length) && (a = r.length); for (var e = 0, n = Array(a); e < a; e++) n[e] = r[e]; return n; }\nfunction _iterableToArrayLimit(r, l) { var t = null == r ? null : \"undefined\" != typeof Symbol && r[Symbol.iterator] || r[\"@@iterator\"]; if (null != t) { var e, n, i, u, a = [], f = !0, o = !1; try { if (i = (t = t.call(r)).next, 0 === l) { if (Object(t) !== t) return; f = !1; } else for (; !(f = (e = i.call(t)).done) && (a.push(e.value), a.length !== l); f = !0); } catch (r) { o = !0, n = r; } finally { try { if (!f && null != t[\"return\"] && (u = t[\"return\"](), Object(u) !== u)) return; } finally { if (o) throw n; } } return a; } }\nfunction _arrayWithHoles(r) { if (Array.isArray(r)) return r; }\n\n\nfunction GenderDropdown(_ref) {\n  var paymentMethod = _ref.paymentMethod,\n    genders = _ref.genders,\n    handleChange = _ref.handleChange;\n  var translateGender = function translateGender(key) {\n    var translations = {\n      male: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('He/him', 'wc-buckaroo-bpe-gateway'),\n      female: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('She/her', 'wc-buckaroo-bpe-gateway'),\n      they: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('They/them', 'wc-buckaroo-bpe-gateway'),\n      unknown: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('I prefer not to say', 'wc-buckaroo-bpe-gateway')\n    };\n    return translations[key] ? translations[key] : capitalizeFirstLetter(key);\n  };\n  var capitalizeFirstLetter = function capitalizeFirstLetter(string) {\n    return string.charAt(0).toUpperCase() + string.slice(1);\n  };\n  var genderOptions = '';\n  genderOptions = Object.entries(genders[paymentMethod]).map(function (_ref2) {\n    var _ref3 = _slicedToArray(_ref2, 2),\n      key = _ref3[0],\n      value = _ref3[1];\n    return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"option\", {\n      value: value\n    }, translateGender(key));\n  });\n  return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"div\", {\n    className: \"payment_box payment_method_\".concat(paymentMethod)\n  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"div\", {\n    className: \"form-row form-row-wide\"\n  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"label\", {\n    htmlFor: \"\".concat(paymentMethod, \"-gender\")\n  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Gender:', 'wc-buckaroo-bpe-gateway'), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"span\", {\n    className: \"required\"\n  }, \"*\")), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"select\", {\n    className: \"buckaroo-custom-select\",\n    name: \"\".concat(paymentMethod, \"-gender\"),\n    id: \"\".concat(paymentMethod, \"-gender\"),\n    onChange: handleChange\n  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"option\", null, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Select your Gender', 'wc-buckaroo-bpe-gateway')), genderOptions)));\n}\n/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (GenderDropdown);\n\n//# sourceURL=webpack:///./blocks/partials/buckaroo_gender.js?");

/***/ })

}]);