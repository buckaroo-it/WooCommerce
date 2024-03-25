/*! For license information please see checkout.js.LICENSE.txt */
(()=>{"use strict";function e(t){return e="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(e){return typeof e}:function(e){return e&&"function"==typeof Symbol&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e},e(t)}function t(e,t){for(var n=0;n<t.length;n++){var o=t[n];o.enumerable=o.enumerable||!1,o.configurable=!0,"value"in o&&(o.writable=!0),Object.defineProperty(e,r(o.key),o)}}function r(t){var r=function(t,r){if("object"!=e(t)||!t)return t;var n=t[Symbol.toPrimitive];if(void 0!==n){var o=n.call(t,"string");if("object"!=e(o))return o;throw new TypeError("@@toPrimitive must return a primitive value.")}return String(t)}(t);return"symbol"==e(r)?r:r+""}const n=function(){return e=function e(){!function(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}(this,e)},(r=[{key:"init",value:function(){this.onLoad();var e=this;jQuery("body").on("change",".bk-paybybank-radio",(function(){var t=jQuery(".bk-paybybank-radio:checked").val();jQuery(".bk-paybybank-real-value, .buckaroo-paybybank-select").val(t),e.setLogo()})),jQuery("body").on("change",".buckaroo-paybybank-select",(function(){var t=jQuery(this).val();jQuery(".bk-paybybank-real-value").val(t),e.setLogo(),e.setRadioSelectedFromRealValue()})),jQuery("body").on("click",".bk-toggle-wrap",(function(){var t=jQuery(".bk-toggle"),r=jQuery(".bk-toggle-text"),n=t.is(".bk-toggle-down");t.toggleClass("bk-toggle-down bk-toggle-up");var o=r.attr("text-less"),a=r.attr("text-more");n?r.text(o):r.text(a),e.getElementToToggle().toggle(n)}));var t=!1;jQuery(window).on("resize",(function(){var e=jQuery(window).width()<768;t!==e&&(t=e,jQuery(".bk-paybybank-mobile").toggle(t),jQuery(".bk-paybybank-not-mobile").toggle(!t))}))}},{key:"setRadioSelectedFromRealValue",value:function(){var e=jQuery('.bk-paybybank-radio[value="'+jQuery(".bk-paybybank-real-value").val()+'"]');e.length&&(jQuery(".bk-toggle").removeClass("bk-toggle-up").addClass("bk-toggle-down"),jQuery(".bk-toggle-text").text(jQuery(".bk-toggle-text").attr("text-more")),jQuery(".custom-radio").hide(),e.closest(".custom-radio").show(),e.prop("checked",!0))}},{key:"setLogo",value:function(){var e=jQuery(".bk-paybybank-real-value").val();buckaroo_global.payByBankLogos&&e&&e.length&&buckaroo_global.payByBankLogos[e]&&jQuery(".payment_method_buckaroo_paybybank > label > img").prop("src",buckaroo_global.payByBankLogos[e])}},{key:"onLoad",value:function(){this.setLogo();var e=jQuery(window).width()<768;jQuery(".bk-paybybank-mobile").toggle(e),jQuery(".bk-paybybank-not-mobile").toggle(!e),this.getElementToToggle().hide()}},{key:"getElementToToggle",value:function(){return jQuery(".bank-method-input:checked").length>0?jQuery(".bank-method-input:not(:checked)").closest(".custom-radio"):jQuery(".bk-paybybank-selector .custom-radio:nth-child(n+5)")}}])&&t(e.prototype,r),Object.defineProperty(e,"prototype",{writable:!1}),e;var e,r}();function o(e){return o="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(e){return typeof e}:function(e){return e&&"function"==typeof Symbol&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e},o(e)}function a(e,t){for(var r=0;r<t.length;r++){var n=t[r];n.enumerable=n.enumerable||!1,n.configurable=!0,"value"in n&&(n.writable=!0),Object.defineProperty(e,i(n.key),n)}}function i(e){var t=function(e,t){if("object"!=o(e)||!e)return e;var r=e[Symbol.toPrimitive];if(void 0!==r){var n=r.call(e,"string");if("object"!=o(n))return n;throw new TypeError("@@toPrimitive must return a primitive value.")}return String(e)}(e);return"symbol"==o(t)?t:t+""}const u=function(){return e=function e(){!function(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}(this,e)},(t=[{key:"listen",value:function(){var e=new n,t=this;e.init(),jQuery("body").on("change",'input[name="payment_method"]',(function(){jQuery("body").trigger("update_checkout")})),jQuery("body").on("updated_checkout",(function(r){t.afterpay(),t.afterpaynew(),t.bilink(),t.klarna(),e.onLoad()})),jQuery("#billing_company").on("input",(function(){t.bilink_toggle(jQuery(this).val()),jQuery("#buckaroo-afterpaynew-company-coc-registration").parent().toggle(0!==jQuery.trim(jQuery(this).val()).length)})),jQuery("body").on("change","#buckaroo-afterpay-b2b",(function(){var e=jQuery(this).is(":checked"),t=jQuery("#buckaroo-afterpay-birthdate"),r=t.parent(),n=jQuery('[name="buckaroo-afterpay-gender"]'),o=n.parent();jQuery("#showB2BBuckaroo").toggle(e),jQuery("#billing_company").length&&jQuery("#buckaroo-afterpay-company-name").val(jQuery("#billing_company").val()),t.prop("disabled",e),r.toggle(!e),r.toggleClass("validate-required",!e),n.prop("disabled",!e),o.toggle(!e)}))}},{key:"afterpay",value:function(){jQuery("input[name=billing_phone]").length&&jQuery("#buckaroo-afterpay-phone").parent().hide()}},{key:"afterpaynew",value:function(){jQuery("input[name=billing_phone]").length&&jQuery("#buckaroo-afterpaynew-phone").parent().hide(),jQuery("#buckaroo-afterpaynew-company-coc-registration").parent().toggle(0!==jQuery.trim(jQuery("input[name=billing_company]").val()).length)}},{key:"bilink",value:function(){var e=jQuery("#billing_company");e.length&&this.bilink_toggle(e.val())}},{key:"bilink_toggle",value:function(e){var t=jQuery("#buckaroo_billink_b2b"),r=jQuery("#buckaroo_billink_b2c");if(t.length&&r.length){var n=jQuery.trim(e).length>0;t.toggle(n),r.toggle(!n)}}},{key:"klarna",value:function(){jQuery("input[name=billing_phone]").length&&jQuery('input[id^="buckaroo-klarna"][type="tel"]').parent().hide()}}])&&a(e.prototype,t),Object.defineProperty(e,"prototype",{writable:!1}),e;var e,t}();var l;!function(e){!function(e){var t;e.validateCardNumber=function(e,t){if(null==e)return!1;if(/[^0-9]+/.test(e))return!1;if(e.length<10||e.length>19)return!1;for(var r=0,n=0;n<e.length;n++){var o=parseInt(e.charAt(n),10);n%2==e.length%2&&(o*=2)>9&&(o-=9),r+=o}if(r%10!=0)return!1;if(null==t)return!0;switch(t.toLowerCase()){case"visa":case"visaelectron":case"vpay":case"cartebleuevisa":case"dankort":return/^4[0-9]{12}(?:[0-9]{3})?$/.test(e);case"postepay":case"mastercard":return/^(5[1-5]|2[2-7])[0-9]{14}$/.test(e);case"bancontactmrcash":case"bancontact":return/^(4796|6060|6703|5613|5614)[0-9]{12,15}$/.test(e);case"maestro":return/^\d{12,19}$/.test(e);case"amex":case"americanexpress":return/^3[47][0-9]{13}$/.test(e);case"cartebancaire":case"cartasi":return/^((5[1-5]|2[2-7])[0-9]{14})|(4[0-9]{12}(?:[0-9]{3})?)$/.test(e);default:return!1}},e.validateCvc=function(e,t){if(null==e)return!1;if(null==t){if(0===e.length)return!0;if(3!==e.length&&4!==e.length)return!1}else switch(t.toLowerCase()){case"bancontactmrcash":case"bancontact":case"maestro":return 0===e.length;case"amex":case"americanexpress":if(4!==e.length)return!1;break;default:if(3!==e.length)return!1}return!/[^0-9]+/.test(e)},e.validateYear=function(e){return null!=e&&!/[^0-9]+/.test(e)&&(2===e.length||4===e.length)},e.validateMonth=function(e){if(null==e)return!1;if(/[^0-9]+/.test(e))return!1;if(1!==e.length&&2!==e.length)return!1;var t=parseInt(e);return!(t<1||t>12)},e.validateCardholderName=function(e){return null!=e&&!(null==(t=e)||t.replace(/\s/g,"").length<1);var t},function(e){e.algorithm="RSA-OAEP",e.hashName="SHA-1",e.exponent="AQAB",e.keyType="RSA",e.modulus="4NdLa7WIq-ygcTo4tGFu8ec7qRwtZ1jLEjKntXfs56gaWtaYSxc-er7ljG22rbv41T5raYfdzvPqV3YcTFCOLpdJIJkzTvorY-IDR09kN6uHKGutSjdkDpYrKFHeU_x0W7P0GUW2Sc14B7G_L8C2eMSqkDAMtANyvOCHdk_2chYOgYqIuZfInTaNEzHbYb6i-D5sKeu1D15G2uEFY-gkuLmtDq3xPUzK_G-haG4KsIL5JKbt-kV3_Dibu3OUpiMDN1YpocqaUR5soFmKiJi1PHtgQZ0aydXxveHIRhtE-5FgL7w307gOqbMJ4q3fXDAZQzKBwlNYnwgAaFW1PSzk9w",e.version="001",e.keyFormat="jwk",e.keyOperations=["encrypt"],e.publicKeyData={alg:e.algorithm,e:e.exponent,ext:!0,kty:e.keyType,n:e.modulus},e.algorithmParams={name:e.algorithm,hash:{name:e.hashName}}}(t||(t={}));var r=function(e){return btoa(String.fromCharCode.apply(null,e))},n=function(e,t,r,n,o){for(var a=unescape(encodeURIComponent(e+","+t+","+r+","+n+","+o)),i=[],u=0;u<a.length;u++)i.push(a.charCodeAt(u));return new Uint8Array(i)};e.encryptCardDataOther=function(e,o,a,i,u){var l=n(e,o,a,i,u);return window.crypto.subtle.importKey(t.keyFormat,t.publicKeyData,t.algorithmParams,!0,t.keyOperations).then((function(e){return window.crypto.subtle.encrypt(t.algorithmParams,e,l.buffer).then((function(e){var n=new Uint8Array(e),o=r(n);return t.version+o}),(function(e){console.log(e)}))}),(function(e){console.log(e)}))},e.encryptCardDataIE=function(e,o,a,i,u,l){for(var c=(window.crypto||window.msCrypto).subtle,y=n(e,o,a,i,u),s={publicKey:'{ \t\t\t\t\t"kty" : "'+t.keyType+'", \t\t\t\t\t"extractable" : true, \t\t\t\t\t"n" : "'+t.modulus+'", \t\t\t\t\t"e" : "'+t.exponent+'", \t\t\t\t\t"alg" : "'+t.algorithm+'" \t\t\t\t}'},b=new Uint8Array(s.publicKey.length),f=0;f<s.publicKey.length;f+=1)b[f]=s.publicKey.charCodeAt(f);var p=c.importKey(t.keyFormat,b,t.algorithmParams,!0,t.keyOperations);p.onerror=function(e){console.error(e)},p.oncomplete=function(e){var n=e.target.result,o=c.encrypt(t.algorithmParams,n,y.buffer);o.onerror=function(e){console.error(e)},o.oncomplete=function(e){var n=new Uint8Array(e.target.result),o=r(n),a=t.version+o;l(a)}}},e.encryptCardData=function(t,r,n,o,a,i){window.navigator.userAgent.indexOf("MSIE ")>0||navigator.userAgent.match(/Trident.*rv\:11\./)?e.encryptCardDataIE(t,r,n,o,a,i):e.encryptCardDataOther(t,r,n,o,a).then((function(e){i(e)}),(function(e){console.log(e)}))}}(e.V001||(e.V001={}))}(l||(l={}));const c=l;function y(e){return y="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(e){return typeof e}:function(e){return e&&"function"==typeof Symbol&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e},y(e)}function s(e,t){for(var r=0;r<t.length;r++){var n=t[r];n.enumerable=n.enumerable||!1,n.configurable=!0,"value"in n&&(n.writable=!0),Object.defineProperty(e,f(n.key),n)}}function b(e,t,r){return(t=f(t))in e?Object.defineProperty(e,t,{value:r,enumerable:!0,configurable:!0,writable:!0}):e[t]=r,e}function f(e){var t=function(e,t){if("object"!=y(e)||!e)return e;var r=e[Symbol.toPrimitive];if(void 0!==r){var n=r.call(e,"string");if("object"!=y(n))return n;throw new TypeError("@@toPrimitive must return a primitive value.")}return String(e)}(e);return"symbol"==y(t)?t:t+""}const p=function(){return e=function e(){!function(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}(this,e),b(this,"form",jQuery("form[name=checkout]")),b(this,"validator",c.V001)},(t=[{key:"listen",value:function(){var e=this.validator,t=this;this.form.on("input",".cardNumber",(function(r){t.toggleClasses(e.validateCardNumber(r.target.value),r.target)})),this.form.on("input",".cvc",(function(r){t.toggleClasses(e.validateCvc(r.target.value),r.target)})),this.form.on("input",".cardHolderName",(function(r){t.toggleClasses(e.validateCardholderName(r.target.value),r.target)})),this.form.on("input",".expirationYear",(function(r){t.toggleClasses(e.validateYear(r.target.value),r.target)})),this.form.on("input",".expirationMonth",(function(r){t.toggleClasses(e.validateMonth(r.target.value),r.target)})),this.form.submit(this.submit.bind(this))}},{key:"toggleClasses",value:function(e,t){(t=jQuery(t)).toggleClass("error",!e),t.toggleClass("validated",e),this.submit()}},{key:"submit",value:function(e){var t=jQuery('input[name="payment_method"]:checked').parent(),r=t.find(".cardNumber").val(),n=t.find(".cvc").val(),o=t.find(".cardHolderName").val(),a=t.find(".expirationYear").val(),i=t.find(".expirationMonth").val(),u=c.V001.validateCardNumber(r),l=c.V001.validateCvc(n),y=c.V001.validateCardholderName(o),s=c.V001.validateYear(a),b=c.V001.validateMonth(i);u&&l&&y&&s&&b&&this.getEncryptedData(r,a,i,n,o,t)}},{key:"getEncryptedData",value:function(e,t,r,n,o,a){c.V001.encryptCardData(e,t,r,n,o,(function(e){a.find(".encryptedCardData").val(e)}))}}])&&s(e.prototype,t),Object.defineProperty(e,"prototype",{writable:!1}),e;var e,t}();function g(e){return g="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(e){return typeof e}:function(e){return e&&"function"==typeof Symbol&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e},g(e)}function m(e,t){for(var r=0;r<t.length;r++){var n=t[r];n.enumerable=n.enumerable||!1,n.configurable=!0,"value"in n&&(n.writable=!0),Object.defineProperty(e,d(n.key),n)}}function d(e){var t=function(e,t){if("object"!=g(e)||!e)return e;var r=e[Symbol.toPrimitive];if(void 0!==r){var n=r.call(e,"string");if("object"!=g(n))return n;throw new TypeError("@@toPrimitive must return a primitive value.")}return String(e)}(e);return"symbol"==g(t)?t:t+""}const v=function(){return e=function e(){!function(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}(this,e)},(t=[{key:"listen",value:function(){var e=this;jQuery("#buckaroo-idin-verify-button").click((function(){jQuery("#buckaroo-idin-issuer")&&jQuery("#buckaroo-idin-issuer").val().length>1?e.identify(jQuery("#buckaroo-idin-issuer").val()):e.displayErrorMessage(buckaroo_global.idin_i18n.bank_required)}))}},{key:"disableBlock",value:function(e){jQuery(e).block({message:null,overlayCSS:{background:"#fff",opacity:.6}})}},{key:"identify",value:function(e){var t=this;t.disableBlock(".checkout.woocommerce-checkout"),jQuery.ajax({url:buckaroo_global.ajax_url,data:{"wc-api":"WC_Gateway_Buckaroo_idin-identify",issuer:e},dataType:"json"}).done((function(e){jQuery(".woocommerce-checkout").unblock(),e&&e.message?t.displayErrorMessage(e.message):e&&"success"==e.result?window.location.replace(e.redirect):t.displayErrorMessage(buckaroo_global.idin_i18n.general_error)})).fail((function(){t.displayErrorMessage(buckaroo_global.idin_i18n.general_error),jQuery(".woocommerce-checkout").unblock()}))}},{key:"displayErrorMessage",value:function(e){var t='      \n        <div class="woocommerce-error" role="alert">\n          '.concat(e,"\n        </div>\n      ");jQuery(".woocommerce-notices-wrapper").first().prepend(t);var r=jQuery(".woocommerce-notices-wrapper .woocommerce-error").first();setTimeout((function(){r.fadeOut(1e3)}),1e4),jQuery("html, body").scrollTop(0)}}])&&m(e.prototype,t),Object.defineProperty(e,"prototype",{writable:!1}),e;var e,t}();jQuery((function(){(new u).listen(),(new p).listen(),(new v).listen()}))})();