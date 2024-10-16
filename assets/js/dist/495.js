"use strict";(self.webpackChunk=self.webpackChunk||[]).push([[495],{1495:(e,t,r)=>{r.r(t),r.d(t,{default:()=>m});var n=r(1609),o=r.n(n),a=r(1912),i=r(1688),l=r(6384),c=r(404);function u(e){return u="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(e){return typeof e}:function(e){return e&&"function"==typeof Symbol&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e},u(e)}function f(e,t,r){return(t=function(e){var t=function(e){if("object"!=u(e)||!e)return e;var t=e[Symbol.toPrimitive];if(void 0!==t){var r=t.call(e,"string");if("object"!=u(r))return r;throw new TypeError("@@toPrimitive must return a primitive value.")}return String(e)}(e);return"symbol"==u(t)?t:t+""}(t))in e?Object.defineProperty(e,t,{value:r,enumerable:!0,configurable:!0,writable:!0}):e[t]=r,e}const m=function(e){var t=e.onStateChange,r=e.methodName,n=e.billing,u=f(f({},"".concat(r,"-phone"),(null==n?void 0:n.phone)||""),"".concat(r,"-birthdate"),""),m=(0,l.A)(u,t),y=m.formState,b=m.updateFormState;return o().createElement("div",null,"NL"===n.country&&o().createElement(a.A,{paymentMethod:r,handleBirthDayChange:function(e){b("".concat(r,"-birthdate"),e)}}),""===n.phone&&o().createElement(c.A,{paymentMethod:r,formState:y,handlePhoneChange:function(e){b("".concat(r,"-phone"),e)}}),o().createElement(i.A,{paymentMethod:r}))}},6384:(e,t,r)=>{r.d(t,{A:()=>u});var n=r(1609);function o(e){return o="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(e){return typeof e}:function(e){return e&&"function"==typeof Symbol&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e},o(e)}function a(e,t){var r=Object.keys(e);if(Object.getOwnPropertySymbols){var n=Object.getOwnPropertySymbols(e);t&&(n=n.filter((function(t){return Object.getOwnPropertyDescriptor(e,t).enumerable}))),r.push.apply(r,n)}return r}function i(e){for(var t=1;t<arguments.length;t++){var r=null!=arguments[t]?arguments[t]:{};t%2?a(Object(r),!0).forEach((function(t){l(e,t,r[t])})):Object.getOwnPropertyDescriptors?Object.defineProperties(e,Object.getOwnPropertyDescriptors(r)):a(Object(r)).forEach((function(t){Object.defineProperty(e,t,Object.getOwnPropertyDescriptor(r,t))}))}return e}function l(e,t,r){return(t=function(e){var t=function(e){if("object"!=o(e)||!e)return e;var t=e[Symbol.toPrimitive];if(void 0!==t){var r=t.call(e,"string");if("object"!=o(r))return r;throw new TypeError("@@toPrimitive must return a primitive value.")}return String(e)}(e);return"symbol"==o(t)?t:t+""}(t))in e?Object.defineProperty(e,t,{value:r,enumerable:!0,configurable:!0,writable:!0}):e[t]=r,e}function c(e,t){(null==t||t>e.length)&&(t=e.length);for(var r=0,n=Array(t);r<t;r++)n[r]=e[r];return n}const u=function(e,t){var r,o,a=(r=(0,n.useState)(e),o=2,function(e){if(Array.isArray(e))return e}(r)||function(e,t){var r=null==e?null:"undefined"!=typeof Symbol&&e[Symbol.iterator]||e["@@iterator"];if(null!=r){var n,o,a,i,l=[],c=!0,u=!1;try{if(a=(r=r.call(e)).next,0===t){if(Object(r)!==r)return;c=!1}else for(;!(c=(n=a.call(r)).done)&&(l.push(n.value),l.length!==t);c=!0);}catch(e){u=!0,o=e}finally{try{if(!c&&null!=r.return&&(i=r.return(),Object(i)!==i))return}finally{if(u)throw o}}return l}}(r,o)||function(e,t){if(e){if("string"==typeof e)return c(e,t);var r={}.toString.call(e).slice(8,-1);return"Object"===r&&e.constructor&&(r=e.constructor.name),"Map"===r||"Set"===r?Array.from(e):"Arguments"===r||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(r)?c(e,t):void 0}}(r,o)||function(){throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.")}()),u=a[0],f=a[1];return{formState:u,handleChange:function(e){var r=e.target,n=r.name,o=r.value,a=i(i({},u),{},l({},n,o));f(a),t(a)},updateFormState:function(e,r){var n=i(i({},u),{},l({},e,r));f(n),t(n)}}}},1688:(e,t,r)=>{r.d(t,{A:()=>i});var n=r(1609),o=r.n(n),a=r(7723);const i=function(e){return e.title,o().createElement("div",{style:{display:"block",fontSize:".8rem",clear:"both"}},(0,a.__)("Je moet minimaal 18+ zijn om deze dienst te gebruiken. Als je op tijd betaalt, voorkom je extra kosten en zorg je dat je in de toekomst nogmaals gebruik kunt maken van de diensten van {title}. Door verder te gaan, accepteer je de Algemene Voorwaarden en bevestig je dat je de Privacyverklaring en Cookieverklaring hebt gelezen.","wc-buckaroo-bpe-gateway"))}},1912:(e,t,r)=>{r.d(t,{A:()=>u});var n=r(1609),o=r.n(n),a=r(9386),i=r.n(a),l=(r(596),r(7723));function c(e,t){(null==t||t>e.length)&&(t=e.length);for(var r=0,n=Array(t);r<t;r++)n[r]=e[r];return n}const u=function(e){var t,r,a=e.paymentMethod,u=e.handleBirthDayChange,f=(t=(0,n.useState)(null),r=2,function(e){if(Array.isArray(e))return e}(t)||function(e,t){var r=null==e?null:"undefined"!=typeof Symbol&&e[Symbol.iterator]||e["@@iterator"];if(null!=r){var n,o,a,i,l=[],c=!0,u=!1;try{if(a=(r=r.call(e)).next,0===t){if(Object(r)!==r)return;c=!1}else for(;!(c=(n=a.call(r)).done)&&(l.push(n.value),l.length!==t);c=!0);}catch(e){u=!0,o=e}finally{try{if(!c&&null!=r.return&&(i=r.return(),Object(i)!==i))return}finally{if(u)throw o}}return l}}(t,r)||function(e,t){if(e){if("string"==typeof e)return c(e,t);var r={}.toString.call(e).slice(8,-1);return"Object"===r&&e.constructor&&(r=e.constructor.name),"Map"===r||"Set"===r?Array.from(e):"Arguments"===r||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(r)?c(e,t):void 0}}(t,r)||function(){throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.")}()),m=f[0],y=f[1];return o().createElement("div",{className:"form-row form-row-wide validate-required"},o().createElement("label",{htmlFor:"".concat(a,"-birthdate")},(0,l.__)("Birthdate (format DD-MM-YYYY):","wc-buckaroo-bpe-gateway"),o().createElement("span",{className:"required"},"*")),o().createElement(i(),{id:"".concat(a,"-birthdate"),name:"".concat(a,"-birthdate"),selected:m,onChange:function(e){var t=e.toLocaleDateString("en-GB",{day:"2-digit",month:"2-digit",year:"numeric"}).replace(/\//g,"-");y(e),u(t)},dateFormat:"dd-MM-yyyy",className:"input-text",autoComplete:"off",placeholderText:"DD-MM-YYYY",showYearDropdown:!0,scrollableYearDropdown:!0,yearDropdownItemNumber:100,minDate:new Date(1900,0,1),maxDate:new Date,showMonthDropdown:!0}))}},404:(e,t,r)=>{r.d(t,{A:()=>i});var n=r(1609),o=r.n(n),a=r(7723);const i=function(e){var t=e.paymentMethod,r=e.formState,n=e.handlePhoneChange;return o().createElement("div",{className:"form-row validate-required"},o().createElement("label",{htmlFor:"".concat(t,"-phone")},(0,a.__)("Phone Number:","wc-buckaroo-bpe-gateway"),o().createElement("span",{className:"required"},"*")),o().createElement("input",{id:"".concat(t,"-phone"),name:"".concat(t,"-phone"),className:"input-text",type:"tel",autoComplete:"off",value:r["".concat(t,"-phone")]||"",onChange:function(e){var t=e.target.value;n(t)}}))}}}]);