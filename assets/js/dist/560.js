"use strict";(self.webpackChunk=self.webpackChunk||[]).push([[560],{7560:(e,t,r)=>{r.r(t),r.d(t,{default:()=>i});var n=r(9196),a=r.n(n),o=r(8446),c=r(1208);function l(e){return l="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(e){return typeof e}:function(e){return e&&"function"==typeof Symbol&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e},l(e)}const i=function(e){var t=e.onStateChange,r=e.methodName,n=e.gateway.genders;return a().createElement("div",{id:"buckaroo_klarnapay"},a().createElement(o.Z,{paymentMethod:r,genders:n,handleChange:function(e){var n=e.target.value;t(function(e,t,r){var n;return n=function(e,t){if("object"!=l(e)||!e)return e;var r=e[Symbol.toPrimitive];if(void 0!==r){var n=r.call(e,"string");if("object"!=l(n))return n;throw new TypeError("@@toPrimitive must return a primitive value.")}return String(e)}(t),(t="symbol"==l(n)?n:String(n))in e?Object.defineProperty(e,t,{value:r,enumerable:!0,configurable:!0,writable:!0}):e[t]=r,e}({},"".concat(r,"-gender"),n))}}),a().createElement(c.Z,null))}},1208:(e,t,r)=>{r.d(t,{Z:()=>c});var n=r(9196),a=r.n(n),o=r(5736);const c=function(e){return e.title,a().createElement("div",{style:{display:"block",fontSize:".8rem",clear:"both"}},(0,o.__)("Je moet minimaal 18+ zijn om deze dienst te gebruiken. Als je op tijd betaalt, voorkom je extra kosten en zorg je dat je in de toekomst nogmaals gebruik kunt maken van de diensten van {title}. Door verder te gaan, accepteer je de Algemene Voorwaarden en bevestig je dat je de Privacyverklaring en Cookieverklaring hebt gelezen.","wc-buckaroo-bpe-gateway"))}},8446:(e,t,r)=>{r.d(t,{Z:()=>l});var n=r(9196),a=r.n(n),o=r(5736);function c(e,t){(null==t||t>e.length)&&(t=e.length);for(var r=0,n=new Array(t);r<t;r++)n[r]=e[r];return n}const l=function(e){var t,r=e.paymentMethod,n=e.genders,l=e.handleChange;return t=Object.entries(n[r]).map((function(e){var t,r,n=(r=2,function(e){if(Array.isArray(e))return e}(t=e)||function(e,t){var r=null==e?null:"undefined"!=typeof Symbol&&e[Symbol.iterator]||e["@@iterator"];if(null!=r){var n,a,o,c,l=[],i=!0,u=!1;try{if(o=(r=r.call(e)).next,0===t){if(Object(r)!==r)return;i=!1}else for(;!(i=(n=o.call(r)).done)&&(l.push(n.value),l.length!==t);i=!0);}catch(e){u=!0,a=e}finally{try{if(!i&&null!=r.return&&(c=r.return(),Object(c)!==c))return}finally{if(u)throw a}}return l}}(t,r)||function(e,t){if(e){if("string"==typeof e)return c(e,t);var r=Object.prototype.toString.call(e).slice(8,-1);return"Object"===r&&e.constructor&&(r=e.constructor.name),"Map"===r||"Set"===r?Array.from(e):"Arguments"===r||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(r)?c(e,t):void 0}}(t,r)||function(){throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.")}()),l=n[0],i=n[1];return a().createElement("option",{value:i},function(e){var t,r={male:(0,o.__)("He/him","wc-buckaroo-bpe-gateway"),female:(0,o.__)("She/her","wc-buckaroo-bpe-gateway"),they:(0,o.__)("They/them","wc-buckaroo-bpe-gateway"),unknown:(0,o.__)("I prefer not to say","wc-buckaroo-bpe-gateway")};return r[e]?r[e]:(t=e).charAt(0).toUpperCase()+t.slice(1)}(l))})),a().createElement("div",{className:"payment_box payment_method_".concat(r)},a().createElement("div",{className:"form-row form-row-wide"},a().createElement("label",{htmlFor:"".concat(r,"-gender")},(0,o.__)("Gender:","wc-buckaroo-bpe-gateway"),a().createElement("span",{className:"required"},"*")),a().createElement("select",{className:"buckaroo-custom-select",name:"".concat(r,"-gender"),id:"".concat(r,"-gender"),onChange:l},a().createElement("option",null,(0,o.__)("Select your Gender","wc-buckaroo-bpe-gateway")),t)))}}}]);