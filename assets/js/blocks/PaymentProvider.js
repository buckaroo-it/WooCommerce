import { createContext } from 'react';

const PaymentContext = createContext();

export const defaults = {
    ['buckaroo-afterpay']: {
        [`buckaroo-afterpay-phone`]: '',
        [`buckaroo-afterpay-birthdate`]: '',
        [`buckaroo-afterpay-b2b`]: '',
        [`buckaroo-afterpay-company-coc-registration`]: '',
        [`buckaroo-afterpay-company-name`]: '',
        [`buckaroo-afterpay-accept`]: '',
    },
    ['buckaroo-afterpaynew']: {
        [`buckaroo-afterpaynew-phone`]: '',
        [`buckaroo-afterpaynew-birthdate`]: '',
        [`buckaroo-afterpaynew-company-coc-registration`]: '',
        [`buckaroo-afterpaynew-accept`]: '',
    },
    ['buckaroo-billink']: {
        [`buckaroo-billink-gender`]: '',
        [`buckaroo-billink-birthdate`]: '',
        [`buckaroo-billink-b2b`]: '',
    },
    ['buckaroo-creditcard']: {
        [`buckaroo_creditcard-creditcard-issuer`]: '',
        [`buckaroo_creditcard-encrypted-data`]: '',
    },
    ['buckaroo-ideal']: {
        [`buckaroo-ideal-issuer`]: '',
    },
    ['buckaroo-in3']: {
        [`buckaroo-in3-birthdate`]: '',
        [`buckaroo-in3-phone`]: '',
    },
    ['buckaroo-klarnakp']: {
        [`buckaroo-klarnakp-gender`]: ''
    },
    ['buckaroo-klarnapay']: {
        [`buckaroo-klarnapay-gender`]: ''
    },
    ['buckaroo-klarnapii']: {
        [`buckaroo-klarnapii-gender`]: ''
    },
    ['buckaroo-paybybank']: {
        [`buckaroo-paybybank-issuer`]: '',
    },
    ['buckaroo-payperemail']: {
        [`buckaroo-payperemail-firstname`]: '',
        [`buckaroo-payperemail-lastname`]: '',
        [`buckaroo-payperemail-email`]: '',
        [`buckaroo-payperemail-gender`]: ''
    },
    ['buckaroo-sepadirectdebit']: {
        [`buckaroo-sepadirectdebit-accountname`]: '',
        [`buckaroo-sepadirectdebit-iban`]: '',
        [`buckaroo-sepadirectdebit-bic`]: '',
    }
}
export default PaymentContext;
