import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import DefaultPayment from './default_payment';

buckarooPaymentMethods.paymentMethods.forEach(paymentMethod => {
    import(`./${paymentMethod.id}`)
        .then(({ default: PaymentComponent }) => {
            registerPaymentMethod({
                name: `${paymentMethod.id}`,
                label: paymentMethod.name,
                content: <PaymentComponent />,
                edit: <PaymentComponent />,
                canMakePayment: () => true,
                ariaLabel: `Pay with ${paymentMethod.name}`,
            });
        })
        .catch(error => {
            if (/Cannot find module/.test(error.message)) {
                registerPaymentMethod({
                    name: `${paymentMethod.id}`,
                    label: paymentMethod.name,
                    content: <DefaultPayment paymentName={paymentMethod.name}/>,
                    edit: <DefaultPayment paymentName={paymentMethod.name}/>,
                    canMakePayment: () => true,
                    ariaLabel: `Pay with ${paymentMethod.name}`,
                });
            } else {
                console.error(`Error importing payment method module './${paymentMethod.id}':`, error);
                throw error;
            }
        });
});
