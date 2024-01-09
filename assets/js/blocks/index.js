import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import DefaultPayment from './default_payment';

function BuckarooLabel({image_path, title})
{
    return React.createElement('div', {className: 'buckaroo_method_block'},
        title, React.createElement('img', {src: image_path, className: 'buckaroo_method_block_img'}, null));
}

buckarooPaymentMethods.paymentMethods.forEach(paymentMethod => {
    import(`./${paymentMethod.id}`)
        .then(({ default: PaymentComponent }) => {
            registerPaymentMethod({
                name: `${paymentMethod.id}`,
                label: React.createElement(BuckarooLabel, {image_path: paymentMethod.image, title: paymentMethod.name}),
                content: <PaymentComponent paymentName={paymentMethod.name}/>,
                edit: <PaymentComponent />,
                canMakePayment: () => true,
                ariaLabel: `Pay with ${paymentMethod.name}`,
            });
        })
        .catch(error => {
            if (/Cannot find module/.test(error.message)) {
                registerPaymentMethod({
                    name: `${paymentMethod.id}`,
                    label: React.createElement(BuckarooLabel, {image_path: paymentMethod.image, title: paymentMethod.name}),
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
