import { sprintf, __ } from '@wordpress/i18n';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { decodeEntities } from '@wordpress/html-entities';
import { getSetting } from '@woocommerce/settings';
import Ideal from './ideal'; // Adjust path as necessary

// Dummy data for issuers, replace with actual data retrieval logic
const issuers = [
    { key: 'issuer1', name: 'Issuer 1' },
    { key: 'issuer2', name: 'Issuer 2' },
    // ... other issuers
];

const Dummy = {
    name: 'buckaroo_ideal',
    label: 'iDEAL',
    content: <Ideal issuers={issuers} />, // Use IdealDropdown here
    edit: <Ideal issuers={issuers} />,
    canMakePayment: () => true,
    ariaLabel: 'iDEAL Payment',
    supports: {
    },
};

registerPaymentMethod( Dummy );