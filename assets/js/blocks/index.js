import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import IdealDropdown from './ideal'; // Component for iDEAL with issuers dropdown

// Assuming buckarooPaymentMethods is available globally
buckarooPaymentMethods.paymentMethods.forEach(paymentMethod => {
    console.log(paymentMethod)
    if (paymentMethod.name === 'buckaroo_ideal' && paymentMethod.issuers) { // Check if it's iDEAL and has issuers
        const issuersArray = Object.entries(paymentMethod.issuers).map(([code, issuer]) => ({ code, ...issuer }));

        registerPaymentMethod({
            name: 'buckaroo_ideal',
            label: paymentMethod.name,
            content: <IdealDropdown issuers={issuersArray} />,
            edit: <IdealDropdown issuers={issuersArray} />,
            canMakePayment: () => true,
            ariaLabel: 'Select your iDEAL payment bank',
        });
    } else {
        // For other payment methods without issuers
        registerPaymentMethod({
            name: `${paymentMethod.name}`,
            label: paymentMethod.name,
            content: <div>{`Pay with ${paymentMethod.name}`}</div>, // Simple content
            edit: <div>{`Pay with ${paymentMethod.name}`}</div>,
            canMakePayment: () => true,
            ariaLabel: `Pay with ${paymentMethod.name}`,
        });
    }
});
