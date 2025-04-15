import BuckarooCheckout from './checkout';
import BuckarooCreditCardsHostedFields from './creditcard-hosted-fields';
import BuckarooIdin from './idin';

jQuery( () => {
	new BuckarooCheckout().listen();
	new BuckarooIdin().listen();
	new BuckarooCreditCardsHostedFields().listen();
} );
