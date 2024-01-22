"use strict";
/*
 * ATTENTION: The "eval" devtool has been used (maybe by default in mode: "development").
 * This devtool is neither made for production nor for readable output files.
 * It uses "eval()" calls to create a separate source file in the browser devtools.
 * If you are trying to read the output file, select a different devtool (https://webpack.js.org/configuration/devtool/)
 * or disable the default devtool with "devtool: false".
 * If you are looking for production-ready output files, see mode: "production" (https://webpack.js.org/configuration/mode/).
 */
(self["webpackChunk"] = self["webpackChunk"] || []).push([["blocks_gateways_buckaroo_ideal_js"],{

/***/ "./blocks/gateways/buckaroo_ideal.js":
/*!*******************************************!*\
  !*** ./blocks/gateways/buckaroo_ideal.js ***!
  \*******************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

eval("__webpack_require__.r(__webpack_exports__);\n/* harmony export */ __webpack_require__.d(__webpack_exports__, {\n/* harmony export */   \"default\": () => (__WEBPACK_DEFAULT_EXPORT__)\n/* harmony export */ });\n/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ \"react\");\n/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);\n\nvar IdealDropdown = function IdealDropdown(_ref) {\n  var idealIssuers = _ref.idealIssuers,\n    onSelectIssuer = _ref.onSelectIssuer;\n  return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"div\", {\n    className: \"payment_box payment_method_buckaroo_ideal\"\n  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"div\", {\n    className: \"form-row form-row-wide\"\n  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"select\", {\n    className: \"buckaroo-custom-select\",\n    name: \"buckaroo-ideal-issuer\",\n    id: \"buckaroo-ideal-issuer\",\n    onChange: function onChange(e) {\n      return onSelectIssuer(e.target.value);\n    }\n  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"option\", {\n    value: \"0\"\n  }, \"Select your bank\"), Object.keys(idealIssuers).map(function (issuerCode) {\n    return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(\"option\", {\n      key: issuerCode,\n      value: issuerCode\n    }, idealIssuers[issuerCode].name);\n  }))));\n};\n/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (IdealDropdown);\n\n//# sourceURL=webpack:///./blocks/gateways/buckaroo_ideal.js?");

/***/ })

}]);