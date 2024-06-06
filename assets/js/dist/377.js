"use strict";(self.webpackChunk=self.webpackChunk||[]).push([[377],{8377:(e,t,r)=>{r.r(t),r.d(t,{default:()=>s});var n=r(1609),a=r.n(n),o=r(1912),c=r(1688),i=r(5591),l=r(7723),u=r(404),m=r(6384),f=r(1288);function p(e){return p="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(e){return typeof e}:function(e){return e&&"function"==typeof Symbol&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e},p(e)}function y(e,t,r){var n;return n=function(e,t){if("object"!=p(e)||!e)return e;var r=e[Symbol.toPrimitive];if(void 0!==r){var n=r.call(e,"string");if("object"!=p(n))return n;throw new TypeError("@@toPrimitive must return a primitive value.")}return String(e)}(t),(t="symbol"==p(n)?n:n+"")in e?Object.defineProperty(e,t,{value:r,enumerable:!0,configurable:!0,writable:!0}):e[t]=r,e}const s=function(e){var t=e.onStateChange,r=e.methodName,n=e.gateway,p=n.customer_type,s=n.b2b,b=e.billing,d=y(y(y(y({},"".concat(r,"-phone"),(null==b?void 0:b.phone)||""),"".concat(r,"-birthdate"),""),"".concat(r,"-company-coc-registration"),""),"".concat(r,"-accept"),""),h=(0,m.A)(d,t),v=h.formState,g=h.handleChange,w=h.updateFormState;return a().createElement("div",null,a().createElement(u.A,{paymentMethod:r,formState:v,handlePhoneChange:function(e){w("".concat(r,"-phone"),e)}}),["BE","NL","DE"].includes(b.country)&&a().createElement("div",null,a().createElement(o.A,{paymentMethod:r,handleBirthDayChange:function(e){w("".concat(r,"-birthdate"),e)}})),"NL"===b.country&&"b2c"!==p&&a().createElement(f.A,{methodName:r,handleChange:g}),"FI"===b.country&&a().createElement("p",{className:"form-row form-row-wide validate-required"},a().createElement("label",{htmlFor:"buckaroo-afterpaynew-identification-number"},(0,l.__)("Identification Number:","wc-buckaroo-bpe-gateway"),a().createElement("span",{className:"required"},"*")),a().createElement("input",{id:"buckaroo-afterpaynew-identification-number",name:"buckaroo-afterpaynew-identification-number",className:"input-text",type:"text",maxLength:"250",autoComplete:"off",onChange:g})),a().createElement(i.A,{paymentMethod:r,handleTermsChange:function(e){w("".concat(r,"-accept"),e)},billingData:b,b2b:s}),a().createElement(c.A,{paymentMethod:r}))}},6384:(e,t,r)=>{r.d(t,{A:()=>u});var n=r(1609);function a(e){return a="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(e){return typeof e}:function(e){return e&&"function"==typeof Symbol&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e},a(e)}function o(e,t){var r=Object.keys(e);if(Object.getOwnPropertySymbols){var n=Object.getOwnPropertySymbols(e);t&&(n=n.filter((function(t){return Object.getOwnPropertyDescriptor(e,t).enumerable}))),r.push.apply(r,n)}return r}function c(e){for(var t=1;t<arguments.length;t++){var r=null!=arguments[t]?arguments[t]:{};t%2?o(Object(r),!0).forEach((function(t){i(e,t,r[t])})):Object.getOwnPropertyDescriptors?Object.defineProperties(e,Object.getOwnPropertyDescriptors(r)):o(Object(r)).forEach((function(t){Object.defineProperty(e,t,Object.getOwnPropertyDescriptor(r,t))}))}return e}function i(e,t,r){var n;return n=function(e,t){if("object"!=a(e)||!e)return e;var r=e[Symbol.toPrimitive];if(void 0!==r){var n=r.call(e,"string");if("object"!=a(n))return n;throw new TypeError("@@toPrimitive must return a primitive value.")}return String(e)}(t),(t="symbol"==a(n)?n:n+"")in e?Object.defineProperty(e,t,{value:r,enumerable:!0,configurable:!0,writable:!0}):e[t]=r,e}function l(e,t){(null==t||t>e.length)&&(t=e.length);for(var r=0,n=new Array(t);r<t;r++)n[r]=e[r];return n}const u=function(e,t){var r,a,o=(r=(0,n.useState)(e),a=2,function(e){if(Array.isArray(e))return e}(r)||function(e,t){var r=null==e?null:"undefined"!=typeof Symbol&&e[Symbol.iterator]||e["@@iterator"];if(null!=r){var n,a,o,c,i=[],l=!0,u=!1;try{if(o=(r=r.call(e)).next,0===t){if(Object(r)!==r)return;l=!1}else for(;!(l=(n=o.call(r)).done)&&(i.push(n.value),i.length!==t);l=!0);}catch(e){u=!0,a=e}finally{try{if(!l&&null!=r.return&&(c=r.return(),Object(c)!==c))return}finally{if(u)throw a}}return i}}(r,a)||function(e,t){if(e){if("string"==typeof e)return l(e,t);var r=Object.prototype.toString.call(e).slice(8,-1);return"Object"===r&&e.constructor&&(r=e.constructor.name),"Map"===r||"Set"===r?Array.from(e):"Arguments"===r||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(r)?l(e,t):void 0}}(r,a)||function(){throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.")}()),u=o[0],m=o[1];return{formState:u,handleChange:function(e){var r=e.target,n=r.name,a=r.value,o=c(c({},u),{},i({},n,a));m(o),t(o)},updateFormState:function(e,r){var n=c(c({},u),{},i({},e,r));m(n),t(n)}}}},1288:(e,t,r)=>{r.d(t,{A:()=>c});var n=r(1609),a=r.n(n),o=r(7723);const c=function(e){var t=e.methodName,r=e.handleChange;return a().createElement("p",{className:"form-row form-row-wide validate-required"},a().createElement("label",{htmlFor:"".concat(t,"-company-coc-registration")},(0,o.__)("CoC-number:","wc-buckaroo-bpe-gateway"),a().createElement("span",{className:"required"},"*")),a().createElement("input",{id:"".concat(t,"-company-coc-registration"),name:"".concat(t,"-company-coc-registration"),className:"input-text",type:"text",maxLength:"250",autoComplete:"off",onChange:r}))}},1688:(e,t,r)=>{r.d(t,{A:()=>c});var n=r(1609),a=r.n(n),o=r(7723);const c=function(e){return e.title,a().createElement("div",{style:{display:"block",fontSize:".8rem",clear:"both"}},(0,o.__)("Je moet minimaal 18+ zijn om deze dienst te gebruiken. Als je op tijd betaalt, voorkom je extra kosten en zorg je dat je in de toekomst nogmaals gebruik kunt maken van de diensten van {title}. Door verder te gaan, accepteer je de Algemene Voorwaarden en bevestig je dat je de Privacyverklaring en Cookieverklaring hebt gelezen.","wc-buckaroo-bpe-gateway"))}},1912:(e,t,r)=>{r.d(t,{A:()=>u});var n=r(1609),a=r.n(n),o=r(9386),c=r.n(o),i=(r(596),r(7723));function l(e,t){(null==t||t>e.length)&&(t=e.length);for(var r=0,n=new Array(t);r<t;r++)n[r]=e[r];return n}const u=function(e){var t,r,o=e.paymentMethod,u=e.handleBirthDayChange,m=(t=(0,n.useState)(null),r=2,function(e){if(Array.isArray(e))return e}(t)||function(e,t){var r=null==e?null:"undefined"!=typeof Symbol&&e[Symbol.iterator]||e["@@iterator"];if(null!=r){var n,a,o,c,i=[],l=!0,u=!1;try{if(o=(r=r.call(e)).next,0===t){if(Object(r)!==r)return;l=!1}else for(;!(l=(n=o.call(r)).done)&&(i.push(n.value),i.length!==t);l=!0);}catch(e){u=!0,a=e}finally{try{if(!l&&null!=r.return&&(c=r.return(),Object(c)!==c))return}finally{if(u)throw a}}return i}}(t,r)||function(e,t){if(e){if("string"==typeof e)return l(e,t);var r=Object.prototype.toString.call(e).slice(8,-1);return"Object"===r&&e.constructor&&(r=e.constructor.name),"Map"===r||"Set"===r?Array.from(e):"Arguments"===r||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(r)?l(e,t):void 0}}(t,r)||function(){throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.")}()),f=m[0],p=m[1];return a().createElement("div",{className:"form-row form-row-wide validate-required"},a().createElement("label",{htmlFor:"".concat(o,"-birthdate")},(0,i.__)("Birthdate (format DD-MM-YYYY):","wc-buckaroo-bpe-gateway"),a().createElement("span",{className:"required"},"*")),a().createElement(c(),{id:"".concat(o,"-birthdate"),name:"".concat(o,"-birthdate"),selected:f,onChange:function(e){var t=e.toLocaleDateString("en-GB",{day:"2-digit",month:"2-digit",year:"numeric"}).replace(/\//g,"-");p(e),u(t)},dateFormat:"dd-MM-yyyy",className:"input-text",autoComplete:"off",placeholderText:"DD-MM-YYYY",showYearDropdown:!0,scrollableYearDropdown:!0,yearDropdownItemNumber:100,minDate:new Date(1900,0,1),maxDate:new Date,showMonthDropdown:!0}))}},404:(e,t,r)=>{r.d(t,{A:()=>c});var n=r(1609),a=r.n(n),o=r(7723);const c=function(e){var t=e.paymentMethod,r=e.formState,n=e.handlePhoneChange;return a().createElement("div",{className:"form-row validate-required"},a().createElement("label",{htmlFor:"".concat(t,"-phone")},(0,o.__)("Phone Number:","wc-buckaroo-bpe-gateway"),a().createElement("span",{className:"required"},"*")),a().createElement("input",{id:"".concat(t,"-phone"),name:"".concat(t,"-phone"),className:"input-text",type:"tel",autoComplete:"off",value:r["".concat(t,"-phone")]||"",onChange:function(e){var t=e.target.value;n(t)}}))}},5591:(e,t,r)=>{r.d(t,{A:()=>i});var n=r(1609),a=r.n(n),o=r(7723);function c(e,t){(null==t||t>e.length)&&(t=e.length);for(var r=0,n=new Array(t);r<t;r++)n[r]=e[r];return n}const i=function(e){var t,r,i=e.paymentMethod,l=e.b2b,u=e.handleTermsChange,m=e.billingData,f=(t=(0,n.useState)(!1),r=2,function(e){if(Array.isArray(e))return e}(t)||function(e,t){var r=null==e?null:"undefined"!=typeof Symbol&&e[Symbol.iterator]||e["@@iterator"];if(null!=r){var n,a,o,c,i=[],l=!0,u=!1;try{if(o=(r=r.call(e)).next,0===t){if(Object(r)!==r)return;l=!1}else for(;!(l=(n=o.call(r)).done)&&(i.push(n.value),i.length!==t);l=!0);}catch(e){u=!0,a=e}finally{try{if(!l&&null!=r.return&&(c=r.return(),Object(c)!==c))return}finally{if(u)throw a}}return i}}(t,r)||function(e,t){if(e){if("string"==typeof e)return c(e,t);var r=Object.prototype.toString.call(e).slice(8,-1);return"Object"===r&&e.constructor&&(r=e.constructor.name),"Map"===r||"Set"===r?Array.from(e):"Arguments"===r||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(r)?c(e,t):void 0}}(t,r)||function(){throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.")}()),p=f[0],y=f[1],s="buckaroo_afterpaynew"===i?"buckaroo-afterpaynew-accept":"buckaroo_afterpay"===i?"buckaroo-afterpay-accept":i,b=m.country,d=(0,o.__)("Accept Riverty | AfterPay conditions:","wc-buckaroo-bpe-gateway"),h=function(e){var t={DE:"de_de",NL:"nl_nl",BE:"be_nl",AT:"de_at",NO:"no_en",FI:"fi_en",SE:"se_en",CH:"ch_en"}[e]||"nl_en",r=arguments.length>1&&void 0!==arguments[1]&&arguments[1]?"b2b_invoice":"invoice";return"".concat("https://documents.riverty.com/terms_conditions/payment_methods/").concat(r,"/").concat(t,"/")}(b,l);return"buckaroo-billink"===i&&(d=(0,o.__)("Accept terms of use","wc-buckaroo-bpe-gateway"),h="https://www.billink.nl/app/uploads/2021/05/Gebruikersvoorwaarden-Billink_V11052021.pdf"),a().createElement("div",null,a().createElement("a",{href:"".concat(h),target:"_blank"},d),a().createElement("span",{className:"required"},"*"),a().createElement("input",{id:"".concat(s,"-accept"),name:"".concat(s,"-accept"),type:"checkbox",checked:p,onChange:function(){y(!p),u(!p)}}),a().createElement("p",{className:"required",style:{float:"right"}},"* Required"))}}}]);