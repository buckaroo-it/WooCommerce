/*!
 * Buckaroo Client Side Encryption v1.0.0
 *
 * Copyright Buckaroo
 * Released under the MIT license
 * https://buckaroo.nl
 *
 * Date: 2018-10-18 09:26
 */
let BuckarooClientSideEncryption;
(function (BuckarooClientSideEncryption) {
  let V001;
  (function (V001) {
    const isNullOrWhitespace = function (input) {
      if (typeof input === 'undefined' || input === null) { return true; }
      return input.replace(/\s/g, '').length < 1;
    };
    V001.validateCardNumber = function (cardNumberString, cardService) {
      if (typeof cardNumberString === 'undefined' || cardNumberString === null) { return false; }
      // Accept only digits.
      if (/[^0-9]+/.test(cardNumberString)) { return false; }
      // Accept only card numbers with a length between 10 and 19.
      if (cardNumberString.length < 10 || cardNumberString.length > 19) { return false; }
      // The Luhn Algorithm.
      let sum = 0;
      for (let i = 0; i < cardNumberString.length; i++) {
        let digit = parseInt(cardNumberString.charAt(i), 10);
        if (i % 2 === cardNumberString.length % 2) {
          digit *= 2;
          if (digit > 9) {
            digit -= 9;
          }
        }
        sum += digit;
      }
      if (sum % 10 !== 0) { return false; }
      if (typeof cardService === 'undefined' || cardService === null) {
        // We could not determine the card service, so we don't know how the card number should be formatted, so return true.
        return true;
      }
      switch (cardService.toLowerCase()) {
        case 'visa':
        case 'visaelectron':
        case 'vpay':
        case 'cartebleuevisa':
        case 'dankort':
          return /^4[0-9]{12}(?:[0-9]{3})?$/.test(cardNumberString);
        case 'postepay':
        case 'mastercard':
          return /^(5[1-5]|2[2-7])[0-9]{14}$/.test(cardNumberString);
        case 'bancontactmrcash':
        case 'bancontact':
          return /^(4796|6060|6703|5613|5614)[0-9]{12,15}$/.test(cardNumberString);
        case 'maestro':
          return /^\d{12,19}$/.test(cardNumberString);
        case 'amex':
        case 'americanexpress':
          return /^3[47][0-9]{13}$/.test(cardNumberString);
        case 'cartebancaire':
        case 'cartasi':
          return /^((5[1-5]|2[2-7])[0-9]{14})|(4[0-9]{12}(?:[0-9]{3})?)$/.test(cardNumberString);
        default:
          // Not a card service Buckaroo recognizes, so return false.
          return false;
      }
    };
    V001.validateCvc = function (cvcString, cardService) {
      if (typeof cvcString === 'undefined' || cvcString === null) { return false; }
      // Determine if the cvc has the correct length.
      if (typeof cardService === 'undefined' || cardService === null) {
        // We do not know the card service, so accept cvc length of 0, 3, or 4.
        if (cvcString.length === 0) { return true; }
        if (cvcString.length !== 3 && cvcString.length !== 4) { return false; }
      } else {
        switch (cardService.toLowerCase()) {
          case 'bancontactmrcash':
          case 'bancontact':
          case 'maestro':
            // These card services does not use a cvc so no cvc should be set.
            return cvcString.length === 0;
          case 'amex':
          case 'americanexpress':
            // American Express uses a cvc with 4 digits.
            if (cvcString.length !== 4) { return false; }
            break;
          default:
            // All other card services uses cvc with 3 digits.
            if (cvcString.length !== 3) { return false; }
            break;
        }
      }
      // Accept only digits
      if (/[^0-9]+/.test(cvcString)) { return false; }
      return true;
    };
    V001.validateYear = function (yearString) {
      if (typeof yearString === 'undefined' || yearString === null) { return false; }
      // Accept only digits.
      if (/[^0-9]+/.test(yearString)) { return false; }
      // Only years with a length of 2 or 4 are accepted.
      if (yearString.length !== 2 && yearString.length !== 4) { return false; }
      return true;
    };
    V001.validateMonth = function (monthString) {
      if (typeof monthString === 'undefined' || monthString === null) { return false; }
      // Accept only digits.
      if (/[^0-9]+/.test(monthString)) { return false; }
      // Only months with a length of 1 or 2 are accepted.
      if (monthString.length !== 1 && monthString.length !== 2) { return false; }
      // Check the value of month, it should be between 1 and 12.
      const monthInt = parseInt(monthString);
      if (monthInt < 1 || monthInt > 12) { return false; }
      return true;
    };
    V001.validateCardholderName = function (nameString) {
      if (typeof nameString === 'undefined' || nameString === null) { return false; }
      // Cardholder name should be filled.
      return !isNullOrWhitespace(nameString);
    };
    // Values to use in the encryption process.
    let Variables;
    (function (Variables) {
      Variables.algorithm = 'RSA-OAEP';
      Variables.hashName = 'SHA-1';
      Variables.exponent = 'AQAB';
      Variables.keyType = 'RSA';
      Variables.modulus = '4NdLa7WIq-ygcTo4tGFu8ec7qRwtZ1jLEjKntXfs56gaWtaYSxc-er7ljG22rbv41T5raYfdzvPqV3YcTFCOLpdJIJkzTvorY-IDR09kN6uHKGutSjdkDpYrKFHeU_x0W7P0GUW2Sc14B7G_L8C2eMSqkDAMtANyvOCHdk_2chYOgYqIuZfInTaNEzHbYb6i-D5sKeu1D15G2uEFY-gkuLmtDq3xPUzK_G-haG4KsIL5JKbt-kV3_Dibu3OUpiMDN1YpocqaUR5soFmKiJi1PHtgQZ0aydXxveHIRhtE-5FgL7w307gOqbMJ4q3fXDAZQzKBwlNYnwgAaFW1PSzk9w';
      Variables.version = '001';
      Variables.keyFormat = 'jwk';
      Variables.keyOperations = ['encrypt'];
      Variables.publicKeyData = {
        alg: Variables.algorithm,
        e: Variables.exponent,
        ext: true,
        kty: Variables.keyType,
        n: Variables.modulus,
      };
      Variables.algorithmParams = {
        name: Variables.algorithm,
        hash: { name: Variables.hashName },
      };
    }(Variables || (Variables = {})));
    // Encodes an Uint8 array to a Base64 string.
    const base64EncodeUint8Array = function (uint8Array) { return btoa(String.fromCharCode.apply(null, uint8Array)); };
    // Decodes a Base64 string to an Uint8 array.
    const base64Decode = function (base64String) { return atob(base64String).split('').map((c) => c.charCodeAt(0)); };
    // Creates an Uint8Array of the given card data
    const cardDataToUint8Array = function (cardNumber, year, month, cvc, cardholder) {
      const encryptableString = `${cardNumber},${year},${month},${cvc},${cardholder}`;
      const encryptableUtf8String = unescape(encodeURIComponent(encryptableString));
      const encryptableArray = [];
      for (let i = 0; i < encryptableUtf8String.length; i++) {
        encryptableArray.push(encryptableUtf8String.charCodeAt(i));
      }
      return new Uint8Array(encryptableArray);
    };
    // Is the current browser Internet Explorer?
    const isBrowserInternetExplorer = function () {
      const ua = window.navigator.userAgent;
      const msie = ua.indexOf('MSIE ');
      return msie > 0 || !!navigator.userAgent.match(/Trident.*rv\:11\./);
    };
    // Returns a promise whose result is the base64-encoded encrypted card data, decryptable only by Buckaroo.
    //
    // Usage:
    // All parameter values are strings
    //
    V001.encryptCardDataOther = function (cardNumber, year, month, cvc, cardholder) {
      // Create an encryptable Uint8 array from the input, which will be encrypted.
      const encryptableUint8Array = cardDataToUint8Array(cardNumber, year, month, cvc, cardholder);
      return window.crypto.subtle.importKey(Variables.keyFormat, Variables.publicKeyData, Variables.algorithmParams, true, Variables.keyOperations)
      // Then, encrypt the input data
        .then((publicKey) => window.crypto.subtle.encrypt(Variables.algorithmParams, publicKey, encryptableUint8Array.buffer)
        // Then, encode the encrypted data to Base64 and prepend the encryption version
          .then((encryptedBuffer) => {
            const encryptedUint8Array = new Uint8Array(encryptedBuffer);
            const encryptedBase64String = base64EncodeUint8Array(encryptedUint8Array);
            return Variables.version + encryptedBase64String;
          }, (error) => { console.log(error); }), (error) => { console.log(error); });
    };
    // Because Internet Explorer does not support JavaScript Promises, an Internet Explorer specific encrypt card data function is provided.
    //
    // Usage:
    V001.encryptCardDataIE = function (cardNumber, year, month, cvc, cardholder, callback) {
      const crypto = window.crypto || window.msCrypto;
      const cryptoSubtle = crypto.subtle;
      // Create an encryptable Uint8 array from the input, which will be encrypted.
      const encryptableUint8Array = cardDataToUint8Array(cardNumber, year, month, cvc, cardholder);
      // Get the public key data
      const key = {
        publicKey: `{ \
					"kty" : "${Variables.keyType}", \
					"extractable" : true, \
					"n" : "${Variables.modulus}", \
					"e" : "${Variables.exponent}", \
					"alg" : "${Variables.algorithm}" \
				}`,
      };
      const keyArray = new Uint8Array(key.publicKey.length);
      for (let i = 0; i < key.publicKey.length; i += 1) {
        keyArray[i] = key.publicKey.charCodeAt(i);
      }
      // Import the public key
      const importOperation = cryptoSubtle.importKey(Variables.keyFormat, keyArray, Variables.algorithmParams, true, Variables.keyOperations);
      importOperation.onerror = function (error) {
        console.error(error);
      };
      // When the public key is successfully imported, encrypt the card data
      importOperation.oncomplete = function (event) {
        const publicKey = event.target.result;
        const encryptOperation = cryptoSubtle.encrypt(Variables.algorithmParams, publicKey, encryptableUint8Array.buffer);
        encryptOperation.onerror = function (error) {
          console.error(error);
        };
        // When the card data is successfully encrypted, perform the callback function with the encrypted card data
        encryptOperation.oncomplete = function (e) {
          const encryptedUint8Array = new Uint8Array(e.target.result);
          const encryptedBase64String = base64EncodeUint8Array(encryptedUint8Array);
          const encryptedCardData = Variables.version + encryptedBase64String;
          callback(encryptedCardData);
        };
      };
    };
    V001.encryptCardData = function (cardNumber, year, month, cvc, cardholder, callback) {
      if (isBrowserInternetExplorer()) {
        V001.encryptCardDataIE(cardNumber, year, month, cvc, cardholder, callback);
      } else {
        V001.encryptCardDataOther(cardNumber, year, month, cvc, cardholder)
          .then((encryptedCardData) => {
            callback(encryptedCardData);
          }, (error) => { console.log(error); });
      }
    };
  }(V001 = BuckarooClientSideEncryption.V001 || (BuckarooClientSideEncryption.V001 = {})));
}(BuckarooClientSideEncryption || (BuckarooClientSideEncryption = {})));
// sourceMappingURL=ClientSideEncryption001.js.map

export default BuckarooClientSideEncryption;
