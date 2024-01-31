"use strict";(self.webpackChunk=self.webpackChunk||[]).push([[344],{4344:(e,t,r)=>{r.r(t),r.d(t,{default:()=>s});var n=r(9196),a=r.n(n),o=r(5493),c=r(1208),l=r(1818);const i=function(e){var t=e.onCocInput,r=e.onCompanyInput;return e.onAccountName,a().createElement("span",{id:"showB2BBuckaroo"},a().createElement("p",{className:"form-row form-row-wide validate-required"},"Fill required fields if bill in on the company:"),a().createElement("p",{className:"form-row form-row-wide validate-required"},a().createElement("label",{htmlFor:"buckaroo-afterpay-company-coc-registration"},"COC (KvK) number:",a().createElement("span",{className:"required"},"*")),a().createElement("input",{id:"buckaroo-afterpay-company-coc-registration",name:"buckaroo-afterpay-company-coc-registration",className:"input-text",type:"text",maxLength:"250",autoComplete:"off",onChange:function(e){return t(e.target.value)}})),a().createElement("p",{className:"form-row form-row-wide validate-required"},a().createElement("label",{htmlFor:"buckaroo-afterpay-company-name"},"Name of the organization:",a().createElement("span",{className:"required"},"*")),a().createElement("input",{id:"buckaroo-afterpay-company-name",name:"buckaroo-afterpay-company-name",className:"input-text",type:"text",maxLength:"250",autoComplete:"off",onChange:function(e){return r(e.target.value)}})))};var u=r(3906);function m(e,t){return function(e){if(Array.isArray(e))return e}(e)||function(e,t){var r=null==e?null:"undefined"!=typeof Symbol&&e[Symbol.iterator]||e["@@iterator"];if(null!=r){var n,a,o,c,l=[],i=!0,u=!1;try{if(o=(r=r.call(e)).next,0===t){if(Object(r)!==r)return;i=!1}else for(;!(i=(n=o.call(r)).done)&&(l.push(n.value),l.length!==t);i=!0);}catch(e){u=!0,a=e}finally{try{if(!i&&null!=r.return&&(c=r.return(),Object(c)!==c))return}finally{if(u)throw a}}return l}}(e,t)||function(e,t){if(e){if("string"==typeof e)return f(e,t);var r=Object.prototype.toString.call(e).slice(8,-1);return"Object"===r&&e.constructor&&(r=e.constructor.name),"Map"===r||"Set"===r?Array.from(e):"Arguments"===r||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(r)?f(e,t):void 0}}(e,t)||function(){throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.")}()}function f(e,t){(null==t||t>e.length)&&(t=e.length);for(var r=0,n=new Array(t);r<t;r++)n[r]=e[r];return n}const s=function(e){var t=e.b2b,r=e.billingData,f=e.onPhoneNumberChange,s=e.onCheckboxChange,d=e.onBirthdateChange,p=e.onCocInput,y=e.onCompanyInput,b=e.onAccountName,h="buckaroo-afterpay",v=m((0,n.useState)(!1),2),g=(v[0],v[1]),w=m((0,n.useState)(!1),2),k=w[0],E=w[1];return a().createElement("div",null,a().createElement(u.Z,{paymentMethod:h,billingData:r,onPhoneNumberChange:f}),"enable"===t&&a().createElement("div",null,a().createElement("div",{className:"form-row form-row-wide validate-required"},a().createElement("label",{htmlFor:"buckaroo-afterpay-b2b"},"Checkout for company",a().createElement("input",{id:"buckaroo-afterpay-b2b",name:"buckaroo-afterpay-b2b",type:"checkbox",value:"",onChange:function(e){return t=e.target.checked,void E(t);var t}}))),k&&a().createElement(i,{onCocInput:function(e){p(e)},onCompanyInput:function(e){y(e)},onAccountName:function(e){b(e)}})),"disable"===t&&a().createElement(o.Z,{paymentMethod:h,onBirthdateChange:function(e){d(e)}}),a().createElement(l.Z,{paymentMethod:h,onCheckboxChange:function(e){g(e),s(e)},billingData:r}),a().createElement(c.Z,{paymentMethod:h}))}},1208:(e,t,r)=>{r.d(t,{Z:()=>o});var n=r(9196),a=r.n(n);const o=function(e){var t=e.title;return a().createElement("div",{style:{display:"block",fontSize:".8rem",clear:"both"}},"Je moet minimaal 18+ zijn om deze dienst te gebruiken. Als je op tijd betaalt, voorkom je extra kosten en zorg je dat je in de toekomst nogmaals gebruik kunt maken van de diensten van ",t,". Door verder te gaan, accepteer je de Algemene Voorwaarden en bevestig je dat je de Privacyverklaring en Cookieverklaring hebt gelezen.")}},5493:(e,t,r)=>{r.d(t,{Z:()=>i});var n=r(9196),a=r.n(n),o=r(9198),c=r.n(o);function l(e,t){(null==t||t>e.length)&&(t=e.length);for(var r=0,n=new Array(t);r<t;r++)n[r]=e[r];return n}r(9339);const i=function(e){var t,r,o=e.paymentMethod,i=e.onBirthdateChange,u=(t=(0,n.useState)(null),r=2,function(e){if(Array.isArray(e))return e}(t)||function(e,t){var r=null==e?null:"undefined"!=typeof Symbol&&e[Symbol.iterator]||e["@@iterator"];if(null!=r){var n,a,o,c,l=[],i=!0,u=!1;try{if(o=(r=r.call(e)).next,0===t){if(Object(r)!==r)return;i=!1}else for(;!(i=(n=o.call(r)).done)&&(l.push(n.value),l.length!==t);i=!0);}catch(e){u=!0,a=e}finally{try{if(!i&&null!=r.return&&(c=r.return(),Object(c)!==c))return}finally{if(u)throw a}}return l}}(t,r)||function(e,t){if(e){if("string"==typeof e)return l(e,t);var r=Object.prototype.toString.call(e).slice(8,-1);return"Object"===r&&e.constructor&&(r=e.constructor.name),"Map"===r||"Set"===r?Array.from(e):"Arguments"===r||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(r)?l(e,t):void 0}}(t,r)||function(){throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.")}()),m=u[0],f=u[1];return a().createElement("p",{className:"form-row form-row-wide validate-required"},a().createElement("label",{htmlFor:"".concat(o,"-birthdate")},"Birthdate (format DD-MM-YYYY):",a().createElement("span",{className:"required"},"*")),a().createElement(c(),{id:"".concat(o,"-birthdate"),name:"".concat(o,"-birthdate"),selected:m,onChange:function(e){var t=e.toLocaleDateString("en-GB",{day:"2-digit",month:"2-digit",year:"numeric"}).replace(/\//g,"-");f(e),i(t)},dateFormat:"dd-MM-yyyy",className:"input-text",autoComplete:"off",placeholderText:"DD-MM-YYYY",showYearDropdown:!0,scrollableYearDropdown:!0,yearDropdownItemNumber:100,minDate:new Date(1900,0,1),maxDate:new Date,showMonthDropdown:!0}))}},3906:(e,t,r)=>{r.d(t,{Z:()=>l});var n=r(9196),a=r.n(n),o=r(5736);function c(e,t){(null==t||t>e.length)&&(t=e.length);for(var r=0,n=new Array(t);r<t;r++)n[r]=e[r];return n}const l=function(e){var t,r,l=e.paymentMethod,i=e.billingData,u=e.onPhoneNumberChange,m=(0,o.__)("Phone Number:","wc-buckaroo-bpe-gateway"),f=(t=(0,n.useState)(""),r=2,function(e){if(Array.isArray(e))return e}(t)||function(e,t){var r=null==e?null:"undefined"!=typeof Symbol&&e[Symbol.iterator]||e["@@iterator"];if(null!=r){var n,a,o,c,l=[],i=!0,u=!1;try{if(o=(r=r.call(e)).next,0===t){if(Object(r)!==r)return;i=!1}else for(;!(i=(n=o.call(r)).done)&&(l.push(n.value),l.length!==t);i=!0);}catch(e){u=!0,a=e}finally{try{if(!i&&null!=r.return&&(c=r.return(),Object(c)!==c))return}finally{if(u)throw a}}return l}}(t,r)||function(e,t){if(e){if("string"==typeof e)return c(e,t);var r=Object.prototype.toString.call(e).slice(8,-1);return"Object"===r&&e.constructor&&(r=e.constructor.name),"Map"===r||"Set"===r?Array.from(e):"Arguments"===r||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(r)?c(e,t):void 0}}(t,r)||function(){throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.")}()),s=f[0],d=f[1];return(0,n.useEffect)((function(){i&&(d(i.phone||""),u(i.phone||""))}),[i]),a().createElement("div",{className:"form-row validate-required"},a().createElement("label",{htmlFor:"".concat(l,"-phone")},m,a().createElement("span",{className:"required"},"*")),a().createElement("input",{id:"buckaroo-".concat(l),name:"buckaroo-".concat(l),className:"input-text",type:"tel",autoComplete:"off",value:s,onChange:function(e){u(e.target.value)}}))}},1818:(e,t,r)=>{r.d(t,{Z:()=>l});var n=r(9196),a=r.n(n),o=r(5736);function c(e,t){(null==t||t>e.length)&&(t=e.length);for(var r=0,n=new Array(t);r<t;r++)n[r]=e[r];return n}const l=function(e){var t,r,l=e.paymentMethod,i=e.onCheckboxChange,u=e.billingData,m=(t=(0,n.useState)(!1),r=2,function(e){if(Array.isArray(e))return e}(t)||function(e,t){var r=null==e?null:"undefined"!=typeof Symbol&&e[Symbol.iterator]||e["@@iterator"];if(null!=r){var n,a,o,c,l=[],i=!0,u=!1;try{if(o=(r=r.call(e)).next,0===t){if(Object(r)!==r)return;i=!1}else for(;!(i=(n=o.call(r)).done)&&(l.push(n.value),l.length!==t);i=!0);}catch(e){u=!0,a=e}finally{try{if(!i&&null!=r.return&&(c=r.return(),Object(c)!==c))return}finally{if(u)throw a}}return l}}(t,r)||function(e,t){if(e){if("string"==typeof e)return c(e,t);var r=Object.prototype.toString.call(e).slice(8,-1);return"Object"===r&&e.constructor&&(r=e.constructor.name),"Map"===r||"Set"===r?Array.from(e):"Arguments"===r||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(r)?c(e,t):void 0}}(t,r)||function(){throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.")}()),f=m[0],s=m[1],d={NL:"https://documents.myafterpay.com/consumer-terms-conditions/nl_nl/",BE:[{link:"https://documents.myafterpay.com/consumer-terms-conditions/nl_be/",label:"Riverty | AfterPay conditions (Dutch)"},{link:"https://documents.myafterpay.com/consumer-terms-conditions/fr_be/",label:"Riverty | AfterPay conditions (French)"}],DE:"https://documents.myafterpay.com/consumer-terms-conditions/de_at/",FI:"https://documents.myafterpay.com/consumer-terms-conditions/fi_fi/",AT:"https://documents.myafterpay.com/consumer-terms-conditions/de_at/"},p="buckaroo_afterpaynew"===l?"buckaroo-afterpaynew-accept":"buckaroo_afterpay"===l?"buckaroo-afterpay-accept":l,y=u.country,b=(0,o.__)("Accept Riverty | AfterPay conditions:","wc-buckaroo-bpe-gateway"),h=d[y]||d.NL;return"buckaroo-billink"===l&&(b=(0,o.__)("Accept terms of use","wc-buckaroo-bpe-gateway"),h="https://www.billink.nl/app/uploads/2021/05/Gebruikersvoorwaarden-Billink_V11052021.pdf"),a().createElement("div",null,a().createElement("a",{href:"".concat(h),target:"_blank"},b),a().createElement("span",{className:"required"},"*"),a().createElement("input",{id:"".concat(p,"-accept"),name:"".concat(p,"-accept"),type:"checkbox",value:"ON",checked:f,onChange:function(){s(!f),i(f?"Off":"On")}}),a().createElement("p",{className:"required",style:{float:"right"}},"* Required"))}}}]);