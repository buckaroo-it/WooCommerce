<?php

namespace Buckaroo\Woocommerce\Gateways\GiftCard;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;
use Buckaroo\Woocommerce\ResponseParser\ResponseParser;

class GiftCardProcessor extends AbstractPaymentProcessor {


    /** @inheritDoc */
    public function getAction(): string {
        return 'payRedirect';
    }

    /** @inheritDoc */
    protected function getMethodBody(): array {
        return array(
            'continueOnIncomplete'       => '1',
            'servicesSelectableByClient' => $this->gateway->get_option( 'giftcards', '' ),
        );
    }

    public function unsuccessfulReturnHandler( ResponseParser $responseParser, string $redirectUrl ) {
        if ( $responseParser->isFailed() ) {
            if ( $responseParser->getSubCodeMessage() === 'Failed' ) {
                wc_add_notice(
                    sprintf(
                        __( 'Card number or pin is incorrect for %s', 'wc-buckaroo-bpe-gateway' ),
                        $responseParser->getPaymentMethod()
                    ),
                    'error'
                );
            } else {
                wc_add_notice( __( $responseParser->getStatusMessage(), 'wc-buckaroo-bpe-gateway' ), 'error' );
            }

            return array(
				'redirect' => $redirectUrl,
				'result'   => $redirectUrl,
			);
        }
    }
}
