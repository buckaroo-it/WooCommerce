import * as convert from './helpers/convert';
import Woocommerce from './woocommerce';
import Buckaroo from './buckaroo';
/* global BuckarooSdk, ApplePayError, ApplePaySession */

export default class ApplePay {
  constructor() {
    this.buckaroo = new Buckaroo();
    this.woocommerce = new Woocommerce();
    this.storeInfo = this.woocommerce.getStoreInformation();
    this.selectedShippingMethod = null;
    this.selectedShippingAmount = null;
    this.totalPrice = null;
    this.countryCode = this.storeInfo.country_code;
  }

  static rebuild() {
    jQuery('.applepay-button-container div').remove();
    jQuery('.applepay-button-container').append('<div>');
  }

  init() {
    BuckarooSdk.ApplePay
      .checkApplePaySupport(this.storeInfo.merchant_id)
      .then((isApplePaySupported) => {
        if (isApplePaySupported) {
          const cartItems = this.getItems();
          const shippingMethods = this.woocommerce.getShippingMethods(this.countryCode);
          const firstShippingItem = ApplePay.getFirstShippingItem(shippingMethods);

          const allItems = firstShippingItem !== null
            ? [].concat(cartItems, firstShippingItem)
            : cartItems;
          const totalToPay = ApplePay.sumTotalAmount(allItems);
          console.log(`All items: ${allItems}`);
          const totalItem = {
            label: 'Totaal',
            amount: totalToPay,
            type: 'final',
          };
          console.log(`Total to pay ${totalItem}`);

          if (shippingMethods.length > 0) {
            this.selectedShippingMethod = shippingMethods[0].identifier;
            this.selectedShippingAmount = shippingMethods[0].amount;
          }
          this.totalPrice = totalToPay;

          const requiredContactFields = ['name', 'email', 'postalAddress', 'phone'];
          const applePayOptions = new BuckarooSdk.ApplePay.ApplePayOptions(
            this.storeInfo.store_name,
            this.storeInfo.country_code,
            this.storeInfo.currency_code,
            this.storeInfo.culture_code,
            this.storeInfo.merchant_id,
            allItems,
            totalItem,
            'shipping',
            shippingMethods,
            this.processApplePayCallback.bind(this),
            this.processShippingMethodsCallback.bind(this),
            this.processChangeContactInfoCallback.bind(this),
            requiredContactFields,
            requiredContactFields,
          );
          const applePayPayment = new BuckarooSdk.ApplePay.ApplePayPayment(
            '.applepay-button-container div',
            applePayOptions,
          );
          applePayPayment.showPayButton('black');
        }
      });
  }

  processChangeContactInfoCallback(contactInfo) {
    this.countryCode = contactInfo.countryCode;

    const cartItems = this.getItems();
    const shippingMethods = this.woocommerce.getShippingMethods(this.countryCode);
    const firstShippingItem = ApplePay.getFirstShippingItem(shippingMethods);

    const allItems = firstShippingItem !== null
      ? [].concat(cartItems, firstShippingItem)
      : cartItems;

    const totalToPay = ApplePay.sumTotalAmount(allItems);

    const totalItem = {
      label: 'Totaal',
      amount: totalToPay,
      type: 'final',
    };

    const info = {
      newShippingMethods: shippingMethods,
      newTotal: totalItem,
      newLineItems: allItems,
    };

    let errors = {};
    if (shippingMethods.length > 0) {
      this.selectedShippingMethod = shippingMethods[0].identifier;
      this.selectedShippingAmount = shippingMethods[0].amount;
    } else {
      errors = ApplePay.shippingCountryError();
    }

    this.totalPrice = totalToPay;

    return Promise.resolve(
      Object.assign(info, errors),
    );
  }

  processShippingMethodsCallback(selectedMethod) {
    const cartItems = this.getItems();
    const shippingItem = {
      type: 'final',
      label: selectedMethod.label,
      amount: convert.toDecimal(selectedMethod.amount) || 0,
      qty: 1,
    };

    const allItems = [].concat(cartItems, shippingItem);
    const totalToPay = ApplePay.sumTotalAmount(allItems);

    const totalItem = {
      label: 'Totaal',
      amount: totalToPay,
      type: 'final',
    };

    this.selectedShippingMethod = selectedMethod.identifier;
    this.selectedShippingAmount = selectedMethod.amount;
    this.totalPrice = totalToPay;

    return Promise.resolve({
      status: ApplePaySession.STATUS_SUCCESS,
      newTotal: totalItem,
      newLineItems: allItems,
    });
  }

  processApplePayCallback(payment) {
    const authorizationResult = {
      status: ApplePaySession.STATUS_SUCCESS,
      errors: [],
    };

    if (authorizationResult.status === ApplePaySession.STATUS_SUCCESS) {
      this.buckaroo.createTransaction(
        payment,
        this.totalPrice,
        this.selectedShippingMethod,
        this.woocommerce.getItems(this.countryCode),
      );
    } else {
      const errors = authorizationResult.errors.map((error) => error.message).join(' ');

      this.woocommerce.displayErrorMessage(
        `Your payment could not be processed. ${errors}`,
      );
    }

    return Promise.resolve(authorizationResult);
  }

  static sumTotalAmount(items) {
    const total = items.reduce((a, b) => a + b.amount, 0);
    return convert.toDecimal(total);
  }

  static getFirstShippingItem(shippingMethods) {
    if (shippingMethods.length > 0) {
      return {
        type: 'final',
        label: shippingMethods[0].label,
        amount: shippingMethods[0].amount || 0,
        qty: 1,
      };
    }
    return null;
  }

  getItems() {
    return this.woocommerce.getItems(this.countryCode)
      .map((item) => {
        const label = `${item.quantity} x ${item.name}`;
        return {
          type: 'final',
          label: convert.maxCharacters(label, 25),
          amount: convert.toDecimal(item.price),
          qty: item.quantity,
        };
      });
  }

  static shippingCountryError() {
    return {
      errors: [new ApplePayError(
        'shippingContactInvalid',
        'country',
        'Shipping is not available for the selected country',
      )],
    };
  }
}
