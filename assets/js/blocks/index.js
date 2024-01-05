import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import DefaultPayment from './default_payment'; // Component for iDEAL with issuers dropdown

// Assuming buckarooPaymentMethods is available globally
buckarooPaymentMethods.paymentMethods.forEach(paymentMethod => {
    if(paymentMethod.has_fields){

        import(`./${paymentMethod.id}`).then(({ default: PaymentComponent }) => {
            registerPaymentMethod({
                name: `${paymentMethod.id}`,
                label: paymentMethod.name,
                content: <PaymentComponent />,
                edit: <PaymentComponent />,
                canMakePayment: () => true,
                ariaLabel: `Pay with ${paymentMethod.name}`,
            });
        });

    }else{
        registerPaymentMethod({
            name: `${paymentMethod.id}`,
            label: paymentMethod.name,
            content: <DefaultPayment paymentName={paymentMethod.name}/>, // Simple content
            edit: <DefaultPayment paymentName={paymentMethod.name}/>,
            canMakePayment: () => true,
            ariaLabel: `Pay with ${paymentMethod.name}`,
        });

    }

});
