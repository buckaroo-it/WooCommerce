"use strict";
/*
 * ATTENTION: The "eval" devtool has been used (maybe by default in mode: "development").
 * This devtool is neither made for production nor for readable output files.
 * It uses "eval()" calls to create a separate source file in the browser devtools.
 * If you are trying to read the output file, select a different devtool (https://webpack.js.org/configuration/devtool/)
 * or disable the default devtool with "devtool: false".
 * If you are looking for production-ready output files, see mode: "production" (https://webpack.js.org/configuration/mode/).
 */
(self["webpackChunk"] = self["webpackChunk"] || []).push([["blocks_hooks_useFormData_js-blocks_partials_buckaroo_coc_field_js-blocks_partials_buckaroo_fi-317f74"],{

/***/ "./blocks/hooks/useFormData.js":
/*!*************************************!*\
  !*** ./blocks/hooks/useFormData.js ***!
  \*************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

eval("__webpack_require__.r(__webpack_exports__);\n/* harmony export */ __webpack_require__.d(__webpack_exports__, {\n/* harmony export */   \"default\": () => (__WEBPACK_DEFAULT_EXPORT__)\n/* harmony export */ });\n/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ \"react\");\n/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);\nfunction _typeof(o) { \"@babel/helpers - typeof\"; return _typeof = \"function\" == typeof Symbol && \"symbol\" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && \"function\" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? \"symbol\" : typeof o; }, _typeof(o); }\nfunction ownKeys(e, r) { var t = Object.keys(e); if (Object.getOwnPropertySymbols) { var o = Object.getOwnPropertySymbols(e); r && (o = o.filter(function (r) { return Object.getOwnPropertyDescriptor(e, r).enumerable; })), t.push.apply(t, o); } return t; }\nfunction _objectSpread(e) { for (var r = 1; r < arguments.length; r++) { var t = null != arguments[r] ? arguments[r] : {}; r % 2 ? ownKeys(Object(t), !0).forEach(function (r) { _defineProperty(e, r, t[r]); }) : Object.getOwnPropertyDescriptors ? Object.defineProperties(e, Object.getOwnPropertyDescriptors(t)) : ownKeys(Object(t)).forEach(function (r) { Object.defineProperty(e, r, Object.getOwnPropertyDescriptor(t, r)); }); } return e; }\nfunction _defineProperty(e, r, t) { return (r = _toPropertyKey(r)) in e ? Object.defineProperty(e, r, { value: t, enumerable: !0, configurable: !0, writable: !0 }) : e[r] = t, e; }\nfunction _toPropertyKey(t) { var i = _toPrimitive(t, \"string\"); return \"symbol\" == _typeof(i) ? i : i + \"\"; }\nfunction _toPrimitive(t, r) { if (\"object\" != _typeof(t) || !t) return t; var e = t[Symbol.toPrimitive]; if (void 0 !== e) { var i = e.call(t, r || \"default\"); if (\"object\" != _typeof(i)) return i; throw new TypeError(\"@@toPrimitive must return a primitive value.\"); } return (\"string\" === r ? String : Number)(t); }\nfunction _slicedToArray(r, e) { return _arrayWithHoles(r) || _iterableToArrayLimit(r, e) || _unsupportedIterableToArray(r, e) || _nonIterableRest(); }\nfunction _nonIterableRest() { throw new TypeError(\"Invalid attempt to destructure non-iterable instance.\\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.\"); }\nfunction _unsupportedIterableToArray(r, a) { if (r) { if (\"string\" == typeof r) return _arrayLikeToArray(r, a); var t = {}.toString.call(r).slice(8, -1); return \"Object\" === t && r.constructor && (t = r.constructor.name), \"Map\" === t || \"Set\" === t ? Array.from(r) : \"Arguments\" === t || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(t) ? _arrayLikeToArray(r, a) : void 0; } }\nfunction _arrayLikeToArray(r, a) { (null == a || a > r.length) && (a = r.length); for (var e = 0, n = Array(a); e < a; e++) n[e] = r[e]; return n; }\nfunction _iterableToArrayLimit(r, l) { var t = null == r ? null : \"undefined\" != typeof Symbol && r[Symbol.iterator] || r[\"@@iterator\"]; if (null != t) { var e, n, i, u, a = [], f = !0, o = !1; try { if (i = (t = t.call(r)).next, 0 === l) { if (Object(t) !== t) return; f = !1; } else for (; !(f = (e = i.call(t)).done) && (a.push(e.value), a.length !== l); f = !0); } catch (r) { o = !0, n = r; } finally { try { if (!f && null != t[\"return\"] && (u = t[\"return\"](), Object(u) !== u)) return; } finally { if (o) throw n; } } return a; } }\nfunction _arrayWithHoles(r) { if (Array.isArray(r)) return r; }\n\nvar useFormData = function useFormData(initialState, onStateChange) {\n  var _useState = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)(initialState),\n    _useState2 = _slicedToArray(_useState, 2),\n    formState = _useState2[0],\n    setFormState = _useState2[1];\n  var handleChange = function handleChange(e) {\n    var _e$target = e.target,\n      name = _e$target.name,\n      value = _e$target.value;\n    var updatedState = _objectSpread(_objectSpread({}, formState), {}, _defineProperty({}, name, value));\n    setFormState(updatedState);\n    onStateChange(updatedState);\n  };\n  var updateFormState = function updateFormState(fieldName, value) {\n    var updatedState = _objectSpread(_objectSpread({}, formState), {}, _defineProperty({}, fieldName, value));\n    setFormState(updatedState);\n    onStateChange(updatedState);\n  };\n  return {\n    formState: formState,\n    handleChange: handleChange,\n    updateFormState: updateFormState\n  };\n};\n/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (useFormData);\n\n//# sourceURL=webpack:///./blocks/hooks/useFormData.js?");

/***/ }),

/***/ "./blocks/partials/buckaroo_coc_field.js":
/*!***********************************************!*\
  !*** ./blocks/partials/buckaroo_coc_field.js ***!
  \***********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

eval("__webpack_require__.r(__webpack_exports__);\n/* harmony export */ __webpack_require__.d(__webpack_exports__, {\n/* harmony export */   \"default\": () => (__WEBPACK_DEFAULT_EXPORT__)\n/* harmony export */ });\n/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ \"react\");\n/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);\n/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ \"@wordpress/i18n\");\n/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);\n\n\nfunction CoCField(_ref) {\n  var methodName = _ref.methodName,\n    handleChange = _ref.handleChange;\n  return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"p\", {\n    className: \"form-row form-row-wide validate-required\"\n  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"label\", {\n    htmlFor: \"\".concat(methodName, \"-company-coc-registration\")\n  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('CoC-number:', 'wc-buckaroo-bpe-gateway'), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"span\", {\n    className: \"required\"\n  }, \"*\")), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"input\", {\n    id: \"\".concat(methodName, \"-company-coc-registration\"),\n    name: \"\".concat(methodName, \"-company-coc-registration\"),\n    className: \"input-text\",\n    type: \"text\",\n    maxLength: \"250\",\n    autoComplete: \"off\",\n    onChange: handleChange\n  }));\n}\n/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (CoCField);\n\n//# sourceURL=webpack:///./blocks/partials/buckaroo_coc_field.js?");

/***/ }),

/***/ "./blocks/partials/buckaroo_financial_warning.js":
/*!*******************************************************!*\
  !*** ./blocks/partials/buckaroo_financial_warning.js ***!
  \*******************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

eval("__webpack_require__.r(__webpack_exports__);\n/* harmony export */ __webpack_require__.d(__webpack_exports__, {\n/* harmony export */   \"default\": () => (__WEBPACK_DEFAULT_EXPORT__)\n/* harmony export */ });\n/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ \"react\");\n/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);\n/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ \"@wordpress/i18n\");\n/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);\n\n\nfunction FinancialWarning(_ref) {\n  var title = _ref.title;\n  return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"div\", {\n    style: {\n      display: 'block',\n      fontSize: '.8rem',\n      clear: 'both'\n    }\n  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Je moet minimaal 18+ zijn om deze dienst te gebruiken. Als je op tijd betaalt, voorkom je extra kosten en zorg je dat je in de toekomst nogmaals gebruik kunt maken van de diensten van {title}. Door verder te gaan, accepteer je de Algemene Voorwaarden en bevestig je dat je de Privacyverklaring en Cookieverklaring hebt gelezen.', 'wc-buckaroo-bpe-gateway'));\n}\n/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (FinancialWarning);\n\n//# sourceURL=webpack:///./blocks/partials/buckaroo_financial_warning.js?");

/***/ }),

/***/ "./blocks/partials/buckaroo_partial_birth_field.js":
/*!*********************************************************!*\
  !*** ./blocks/partials/buckaroo_partial_birth_field.js ***!
  \*********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

eval("__webpack_require__.r(__webpack_exports__);\n/* harmony export */ __webpack_require__.d(__webpack_exports__, {\n/* harmony export */   \"default\": () => (__WEBPACK_DEFAULT_EXPORT__)\n/* harmony export */ });\n/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ \"react\");\n/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);\n/* harmony import */ var react_datepicker__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! react-datepicker */ \"./node_modules/react-datepicker/dist/react-datepicker.min.js\");\n/* harmony import */ var react_datepicker__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(react_datepicker__WEBPACK_IMPORTED_MODULE_3__);\n/* harmony import */ var react_datepicker_dist_react_datepicker_css__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react-datepicker/dist/react-datepicker.css */ \"./node_modules/react-datepicker/dist/react-datepicker.css\");\n/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ \"@wordpress/i18n\");\n/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);\nfunction _slicedToArray(r, e) { return _arrayWithHoles(r) || _iterableToArrayLimit(r, e) || _unsupportedIterableToArray(r, e) || _nonIterableRest(); }\nfunction _nonIterableRest() { throw new TypeError(\"Invalid attempt to destructure non-iterable instance.\\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.\"); }\nfunction _unsupportedIterableToArray(r, a) { if (r) { if (\"string\" == typeof r) return _arrayLikeToArray(r, a); var t = {}.toString.call(r).slice(8, -1); return \"Object\" === t && r.constructor && (t = r.constructor.name), \"Map\" === t || \"Set\" === t ? Array.from(r) : \"Arguments\" === t || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(t) ? _arrayLikeToArray(r, a) : void 0; } }\nfunction _arrayLikeToArray(r, a) { (null == a || a > r.length) && (a = r.length); for (var e = 0, n = Array(a); e < a; e++) n[e] = r[e]; return n; }\nfunction _iterableToArrayLimit(r, l) { var t = null == r ? null : \"undefined\" != typeof Symbol && r[Symbol.iterator] || r[\"@@iterator\"]; if (null != t) { var e, n, i, u, a = [], f = !0, o = !1; try { if (i = (t = t.call(r)).next, 0 === l) { if (Object(t) !== t) return; f = !1; } else for (; !(f = (e = i.call(t)).done) && (a.push(e.value), a.length !== l); f = !0); } catch (r) { o = !0, n = r; } finally { try { if (!f && null != t[\"return\"] && (u = t[\"return\"](), Object(u) !== u)) return; } finally { if (o) throw n; } } return a; } }\nfunction _arrayWithHoles(r) { if (Array.isArray(r)) return r; }\n\n\n\n\nfunction BirthDayField(_ref) {\n  var paymentMethod = _ref.paymentMethod,\n    handleBirthDayChange = _ref.handleBirthDayChange;\n  var _useState = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)(null),\n    _useState2 = _slicedToArray(_useState, 2),\n    birthdate = _useState2[0],\n    setBirthdate = _useState2[1];\n  var handleDateChange = function handleDateChange(date) {\n    var formattedDate = date.toLocaleDateString('en-GB', {\n      day: '2-digit',\n      month: '2-digit',\n      year: 'numeric'\n    }).replace(/\\//g, '-');\n    setBirthdate(date);\n    handleBirthDayChange(formattedDate);\n  };\n  return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"div\", {\n    className: \"form-row form-row-wide validate-required\"\n  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"label\", {\n    htmlFor: \"\".concat(paymentMethod, \"-birthdate\")\n  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Birthdate (format DD-MM-YYYY):', 'wc-buckaroo-bpe-gateway'), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"span\", {\n    className: \"required\"\n  }, \"*\")), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement((react_datepicker__WEBPACK_IMPORTED_MODULE_3___default()), {\n    id: \"\".concat(paymentMethod, \"-birthdate\"),\n    name: \"\".concat(paymentMethod, \"-birthdate\"),\n    selected: birthdate,\n    onChange: handleDateChange,\n    dateFormat: \"dd-MM-yyyy\",\n    className: \"input-text\",\n    autoComplete: \"off\",\n    placeholderText: \"DD-MM-YYYY\",\n    showYearDropdown: true,\n    scrollableYearDropdown: true,\n    yearDropdownItemNumber: 100,\n    minDate: new Date(1900, 0, 1),\n    maxDate: new Date(),\n    showMonthDropdown: true\n  }));\n}\n/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (BirthDayField);\n\n//# sourceURL=webpack:///./blocks/partials/buckaroo_partial_birth_field.js?");

/***/ }),

/***/ "./blocks/partials/buckaroo_terms_and_condition.js":
/*!*********************************************************!*\
  !*** ./blocks/partials/buckaroo_terms_and_condition.js ***!
  \*********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

eval("__webpack_require__.r(__webpack_exports__);\n/* harmony export */ __webpack_require__.d(__webpack_exports__, {\n/* harmony export */   \"default\": () => (__WEBPACK_DEFAULT_EXPORT__)\n/* harmony export */ });\n/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ \"react\");\n/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);\n/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ \"@wordpress/i18n\");\n/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);\nfunction _slicedToArray(r, e) { return _arrayWithHoles(r) || _iterableToArrayLimit(r, e) || _unsupportedIterableToArray(r, e) || _nonIterableRest(); }\nfunction _nonIterableRest() { throw new TypeError(\"Invalid attempt to destructure non-iterable instance.\\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.\"); }\nfunction _unsupportedIterableToArray(r, a) { if (r) { if (\"string\" == typeof r) return _arrayLikeToArray(r, a); var t = {}.toString.call(r).slice(8, -1); return \"Object\" === t && r.constructor && (t = r.constructor.name), \"Map\" === t || \"Set\" === t ? Array.from(r) : \"Arguments\" === t || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(t) ? _arrayLikeToArray(r, a) : void 0; } }\nfunction _arrayLikeToArray(r, a) { (null == a || a > r.length) && (a = r.length); for (var e = 0, n = Array(a); e < a; e++) n[e] = r[e]; return n; }\nfunction _iterableToArrayLimit(r, l) { var t = null == r ? null : \"undefined\" != typeof Symbol && r[Symbol.iterator] || r[\"@@iterator\"]; if (null != t) { var e, n, i, u, a = [], f = !0, o = !1; try { if (i = (t = t.call(r)).next, 0 === l) { if (Object(t) !== t) return; f = !1; } else for (; !(f = (e = i.call(t)).done) && (a.push(e.value), a.length !== l); f = !0); } catch (r) { o = !0, n = r; } finally { try { if (!f && null != t[\"return\"] && (u = t[\"return\"](), Object(u) !== u)) return; } finally { if (o) throw n; } } return a; } }\nfunction _arrayWithHoles(r) { if (Array.isArray(r)) return r; }\n\n\nfunction TermsAndConditionsCheckbox(_ref) {\n  var paymentMethod = _ref.paymentMethod,\n    b2b = _ref.b2b,\n    handleTermsChange = _ref.handleTermsChange,\n    billingData = _ref.billingData;\n  var _useState = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)(false),\n    _useState2 = _slicedToArray(_useState, 2),\n    isChecked = _useState2[0],\n    setIsChecked = _useState2[1];\n  var getTermsUrl = function getTermsUrl(country) {\n    var isB2B = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : false;\n    var baseUrl = 'https://documents.riverty.com/terms_conditions/payment_methods/';\n    var languageMap = {\n      DE: 'de_de',\n      NL: 'nl_nl',\n      BE: 'be_nl',\n      AT: 'de_at',\n      NO: 'no_en',\n      FI: 'fi_en',\n      SE: 'se_en',\n      CH: 'ch_en'\n    };\n    var languageCode = languageMap[country] || 'nl_en';\n    var path = isB2B ? 'b2b_invoice' : 'invoice';\n    return \"\".concat(baseUrl).concat(path, \"/\").concat(languageCode, \"/\");\n  };\n  var fieldName = paymentMethod === 'buckaroo_afterpaynew' ? 'buckaroo-afterpaynew-accept' : paymentMethod === 'buckaroo_afterpay' ? 'buckaroo-afterpay-accept' : paymentMethod;\n  var country = billingData.country;\n  var labelText = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Accept Riverty conditions:', 'wc-buckaroo-bpe-gateway');\n  var termsUrl = getTermsUrl(country, b2b);\n  if (paymentMethod === 'buckaroo-billink') {\n    labelText = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Accept terms of use', 'wc-buckaroo-bpe-gateway');\n    termsUrl = 'https://www.billink.nl/app/uploads/2021/05/Gebruikersvoorwaarden-Billink_V11052021.pdf';\n  }\n  var handleCheckboxChange = function handleCheckboxChange() {\n    setIsChecked(!isChecked);\n    handleTermsChange(!isChecked);\n  };\n  return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"div\", null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"a\", {\n    href: \"\".concat(termsUrl),\n    target: \"_blank\",\n    rel: \"noreferrer\"\n  }, labelText), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"span\", {\n    className: \"required\"\n  }, \"*\"), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"input\", {\n    id: \"\".concat(fieldName, \"-accept\"),\n    name: \"\".concat(fieldName, \"-accept\"),\n    type: \"checkbox\",\n    checked: isChecked,\n    onChange: handleCheckboxChange\n  }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"p\", {\n    className: \"required\",\n    style: {\n      \"float\": 'right'\n    }\n  }, \"* Required\"));\n}\n/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (TermsAndConditionsCheckbox);\n\n//# sourceURL=webpack:///./blocks/partials/buckaroo_terms_and_condition.js?");

/***/ })

}]);