"use strict";(self.webpackChunk=self.webpackChunk||[]).push([[566],{8566:(e,t,r)=>{r.r(t),r.d(t,{default:()=>u});var a=r(9196),n=r.n(a),l=r(8446);function o(e,t){return function(e){if(Array.isArray(e))return e}(e)||function(e,t){var r=null==e?null:"undefined"!=typeof Symbol&&e[Symbol.iterator]||e["@@iterator"];if(null!=r){var a,n,l,o,i=[],u=!0,c=!1;try{if(l=(r=r.call(e)).next,0===t){if(Object(r)!==r)return;u=!1}else for(;!(u=(a=l.call(r)).done)&&(i.push(a.value),i.length!==t);u=!0);}catch(e){c=!0,n=e}finally{try{if(!u&&null!=r.return&&(o=r.return(),Object(o)!==o))return}finally{if(c)throw n}}return i}}(e,t)||function(e,t){if(e){if("string"==typeof e)return i(e,t);var r=Object.prototype.toString.call(e).slice(8,-1);return"Object"===r&&e.constructor&&(r=e.constructor.name),"Map"===r||"Set"===r?Array.from(e):"Arguments"===r||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(r)?i(e,t):void 0}}(e,t)||function(){throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.")}()}function i(e,t){(null==t||t>e.length)&&(t=e.length);for(var r=0,a=new Array(t);r<t;r++)a[r]=e[r];return a}const u=function(e){var t=e.genders,r=e.onSelectGender,i=e.onFirstNameChange,u=e.onLastNameChange,c=e.onEmailChange,m=e.billingData,s=o((0,a.useState)(null),2),f=(s[0],s[1]),p=o((0,a.useState)(""),2),d=p[0],y=p[1],b=o((0,a.useState)(""),2),v=b[0],h=b[1],g=o((0,a.useState)(""),2),E=g[0],k=g[1];return(0,a.useEffect)((function(){m&&(y(m.first_name||""),h(m.last_name||""),k(m.email||""),i(m.first_name||""),u(m.last_name||""),c(m.email||""))}),[m]),n().createElement("div",null,n().createElement(l.Z,{paymentMethod:"buckaroo-payperemail",genders:t,onSelectGender:function(e){f(e),r(e)}}),n().createElement("div",{className:"form-row validate-required"},n().createElement("label",{htmlFor:"buckaroo-payperemail-firstname"},"First Name: ",n().createElement("span",{className:"required"},"*")),n().createElement("input",{id:"buckaroo-payperemail-firstname",name:"buckaroo-payperemail-firstname",className:"input-text",type:"text",autoComplete:"off",value:d,onChange:function(e){y(e.target.value),i(e.target.value)}})),n().createElement("div",{className:"form-row validate-required"},n().createElement("label",{htmlFor:"buckaroo-payperemail-lastname"},"Last Name: ",n().createElement("span",{className:"required"},"*")),n().createElement("input",{id:"buckaroo-payperemail-lastname",name:"buckaroo-payperemail-lastname",className:"input-text",type:"text",autoComplete:"off",value:v,onChange:function(e){h(e.target.value),u(e.target.value)}})),n().createElement("div",{className:"form-row validate-required"},n().createElement("label",{htmlFor:"buckaroo-payperemail-email"},"Email: ",n().createElement("span",{className:"required"},"*")),n().createElement("input",{id:"buckaroo-payperemail-email",name:"buckaroo-payperemail-email",className:"input-text",type:"email",autoComplete:"off",value:E,onChange:function(e){k(e.target.value),c(e.target.value)}})),n().createElement("div",{className:"required",style:{float:"right"}},"* Required"),n().createElement("br",null))}},8446:(e,t,r)=>{r.d(t,{Z:()=>o});var a=r(9196),n=r.n(a);function l(e,t){(null==t||t>e.length)&&(t=e.length);for(var r=0,a=new Array(t);r<t;r++)a[r]=e[r];return a}const o=function(e){var t,r=e.paymentMethod,a=e.genders,o=e.onSelectGender;return t=Object.entries(a[r]).map((function(e){var t,r,a=(r=2,function(e){if(Array.isArray(e))return e}(t=e)||function(e,t){var r=null==e?null:"undefined"!=typeof Symbol&&e[Symbol.iterator]||e["@@iterator"];if(null!=r){var a,n,l,o,i=[],u=!0,c=!1;try{if(l=(r=r.call(e)).next,0===t){if(Object(r)!==r)return;u=!1}else for(;!(u=(a=l.call(r)).done)&&(i.push(a.value),i.length!==t);u=!0);}catch(e){c=!0,n=e}finally{try{if(!u&&null!=r.return&&(o=r.return(),Object(o)!==o))return}finally{if(c)throw n}}return i}}(t,r)||function(e,t){if(e){if("string"==typeof e)return l(e,t);var r=Object.prototype.toString.call(e).slice(8,-1);return"Object"===r&&e.constructor&&(r=e.constructor.name),"Map"===r||"Set"===r?Array.from(e):"Arguments"===r||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(r)?l(e,t):void 0}}(t,r)||function(){throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.")}()),o=a[0],i=a[1];return n().createElement("option",{key:i,value:i},o)})),n().createElement("div",{className:"payment_box payment_method_".concat(r)},n().createElement("div",{className:"form-row form-row-wide"},n().createElement("label",{htmlFor:"".concat(r,"-gender")},"Gender: ",n().createElement("span",{className:"required"},"*")),n().createElement("select",{className:"buckaroo-custom-select",name:"buckaroo-".concat(r),id:"buckaroo-".concat(r),onChange:function(e){return o(e.target.value)}},n().createElement("option",null,"Select your Gender"),t)))}}}]);