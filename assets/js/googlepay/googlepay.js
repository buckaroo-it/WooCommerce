import * as convert from './helpers/convert.js';
import Woocommerce from './woocommerce.js';
import Buckaroo from './buckaroo.js';

export default class GooglePay {
    constructor() {
        this.buckaroo = new Buckaroo();
        this.woocommerce = new Woocommerce();
        this.store_info = this.woocommerce.getStoreInformation();
        this.selected_shipping_method = null;
        this.selected_shipping_amount = null;
        this.total_price = null;
        this.country_code = this.store_info.country_code;
    }

    rebuild() {
        const existing = document.getElementById('googlepay-button-element');
        if (existing) {
            while (existing.firstChild) {
                existing.removeChild(existing.firstChild);
            }
        } else {
            jQuery('.googlepay-button-container div').remove();
            jQuery('.googlepay-button-container').append('<div id="googlepay-button-element">');
        }
    }

    init() {
        const cart_items = this.getItems();
        const shipping_methods = this.woocommerce.getShippingMethods(this.country_code);
        const first_shipping_item = this.getFirstShippingItem(shipping_methods);

        const all_items = first_shipping_item !== null ? [].concat(cart_items, first_shipping_item) : cart_items;

        const total_to_pay = this.sumTotalAmount(all_items);

        if (shipping_methods.length > 0) {
            this.selected_shipping_method = shipping_methods[0].identifier;
            this.selected_shipping_amount = shipping_methods[0].amount;
        }
        this.total_price = total_to_pay;

        const environment = this.store_info.mode === 'live' ? 'PRODUCTION' : 'TEST';
        const buttonStyle = this.store_info.button_style || 'black';

        const googlePayPayment = new BuckarooSdk.GooglePay.GooglePayPayment({
            environment: environment,
            buttonColor: buttonStyle === 'white' ? 'white' : 'black',
            buttonType: 'pay',
            buttonSizeMode: 'fill',
            buttonContainerId: 'googlepay-button-element',
            buttonLocale: this.store_info.locale || 'en',
            totalPriceStatus: 'ESTIMATED',
            totalPrice: String(total_to_pay),
            currencyCode: this.store_info.currency_code,
            countryCode: this.store_info.country_code,
            merchantName: this.store_info.store_name,
            merchantId: this.store_info.google_merchant_id || '',
            gatewayMerchantId: this.store_info.merchant_id,
            shippingAddressRequired: true,
            shippingOptionRequired: true,
            emailRequired: true,
            billingAddressRequired: true,
            billingAddressParameters: {
                format: 'FULL',
                phoneNumberRequired: true,
            },
            onGooglePayLoadError: error => {
                console.error('Error loading GooglePay:', error);
            },
            processPayment: paymentData => {
                return this.processGooglepayCallback(paymentData);
            },
            onPaymentDataChanged: intermediatePaymentData => {
                return this.onPaymentDataChanged(intermediatePaymentData);
            },
        });

        googlePayPayment.initiate();
    }

    onPaymentDataChanged(intermediatePaymentData) {
        const countryCode = intermediatePaymentData.shippingAddress?.countryCode || this.country_code;
        this.country_code = countryCode;

        const shippingMethods = this.woocommerce.getShippingMethods(countryCode);
        const cartItems = this.getItems();

        const shippingOptions = shippingMethods.map((method, index) => ({
            id: method.identifier,
            label: method.label,
            description: '',
        }));

        if (intermediatePaymentData.shippingOptionData?.id) {
            const selectedMethod = shippingMethods.find(
                m => m.identifier === intermediatePaymentData.shippingOptionData.id
            );
            if (selectedMethod) {
                this.selected_shipping_method = selectedMethod.identifier;
                this.selected_shipping_amount = selectedMethod.amount;
            }
        } else if (shippingMethods.length > 0) {
            this.selected_shipping_method = shippingMethods[0].identifier;
            this.selected_shipping_amount = shippingMethods[0].amount;
        }

        const shippingCost = this.selected_shipping_amount || 0;
        const itemsTotal = cartItems.reduce((sum, item) => sum + item.amount, 0);
        const totalPrice = convert.toDecimal(itemsTotal + shippingCost);
        this.total_price = totalPrice;

        const result = {
            newTransactionInfo: {
                totalPriceStatus: 'FINAL',
                totalPrice: String(totalPrice),
                currencyCode: this.store_info.currency_code,
                countryCode: this.store_info.country_code,
            },
        };

        if (shippingOptions.length > 0) {
            result.newShippingOptionParameters = {
                shippingOptions: shippingOptions,
                defaultSelectedOptionId: this.selected_shipping_method || shippingOptions[0].id,
            };
        }

        return Promise.resolve(result);
    }

    processGooglepayCallback(paymentData) {
        const email = paymentData.email || '';
        const billingAddress = paymentData.paymentMethodData.info?.billingAddress || {};
        const shippingAddress = paymentData.shippingAddress || billingAddress;

        const payment = {
            token: paymentData.paymentMethodData.tokenizationData.token,
            billingContact: this.mapGoogleContact(billingAddress, email),
            shippingContact: this.mapGoogleContact(shippingAddress, email),
        };

        this.buckaroo.createTransaction(
            payment,
            this.total_price,
            this.selected_shipping_method,
            this.woocommerce.getItems(this.country_code)
        );

        return Promise.resolve({});
    }

    mapGoogleContact(address, email) {
        const lines = [address.address1 || '', address.address2 || ''].filter(Boolean);
        return {
            givenName: address.name ? address.name.split(' ')[0] : '',
            familyName: address.name ? address.name.split(' ').slice(1).join(' ') : '',
            emailAddress: email || '',
            phoneNumber: address.phoneNumber || '',
            addressLines: lines.length > 0 ? lines : [''],
            locality: address.locality || '',
            postalCode: address.postalCode || '',
            countryCode: address.countryCode || '',
        };
    }

    sumTotalAmount(items) {
        const total = items.reduce((a, b) => a + b.amount, 0);

        return convert.toDecimal(total);
    }

    getFirstShippingItem(shipping_methods) {
        if (shipping_methods.length > 0) {
            return {
                type: 'final',
                label: shipping_methods[0].label,
                amount: shipping_methods[0].amount || 0,
                qty: 1,
            };
        }
        return null;
    }

    getItems() {
        return this.woocommerce.getItems(this.country_code).map(item => {
            const label = `${item.quantity} x ${item.name}`;
            return {
                type: 'final',
                label: convert.maxCharacters(label, 25),
                amount: convert.toDecimal(item.price),
                qty: item.quantity,
            };
        });
    }
}
