<?php

namespace Buckaroo\Woocommerce\Core;

use Buckaroo\Woocommerce\Gateways\Afterpay\AfterpayNewGateway;
use Buckaroo\Woocommerce\Gateways\Afterpay\AfterpayOldGateway;
use Buckaroo\Woocommerce\Gateways\Applepay\ApplepayGateway;
use Buckaroo\Woocommerce\Gateways\Bancontact\BancontactGateway;
use Buckaroo\Woocommerce\Gateways\Belfius\BelfiusGateway;
use Buckaroo\Woocommerce\Gateways\Billink\BillinkGateway;
use Buckaroo\Woocommerce\Gateways\Blik\BlikGateway;
use Buckaroo\Woocommerce\Gateways\CreditCard\CreditCardGateway;
use Buckaroo\Woocommerce\Gateways\Eps\EpsGateway;
use Buckaroo\Woocommerce\Gateways\GiftCard\GiftCardGateway;
use Buckaroo\Woocommerce\Gateways\Ideal\IdealGateway;
use Buckaroo\Woocommerce\Gateways\In3\In3Gateway;
use Buckaroo\Woocommerce\Gateways\Kbc\KbcGateway;
use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaKpGateway;
use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaPayGateway;
use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaPiiGateway;
use Buckaroo\Woocommerce\Gateways\KnakenSettle\KnakenSettleGateway;
use Buckaroo\Woocommerce\Gateways\MbWay\MbWayGateway;
use Buckaroo\Woocommerce\Gateways\Multibanco\MultibancoGateway;
use Buckaroo\Woocommerce\Gateways\PayByBank\PayByBankGateway;
use Buckaroo\Woocommerce\Gateways\Payconiq\PayconiqGateway;
use Buckaroo\Woocommerce\Gateways\Paypal\PaypalGateway;
use Buckaroo\Woocommerce\Gateways\PayPerEmail\PayPerEmailGateway;
use Buckaroo\Woocommerce\Gateways\Przelewy24\Przelewy24Gateway;
use Buckaroo\Woocommerce\Gateways\SepaDirectDebit\SepaDirectDebitGateway;
use Buckaroo\Woocommerce\Gateways\Transfer\TransferGateway;
use Buckaroo\Woocommerce\Order\OrderCapture;

class PaymentGatewayRegistry {

	/**
	 * List of registered payment gateways.
	 *
	 * @var array
	 */
	protected array $gateways = array(
		'ideal'           => array( 'gateway_class' => IdealGateway::class ),
		'afterpay'        => array( 'gateway_class' => AfterpayOldGateway::class ),
		'afterpaynew'     => array( 'gateway_class' => AfterpayNewGateway::class ),
		'applepay'        => array( 'gateway_class' => ApplepayGateway::class ),
		'bancontact'      => array( 'gateway_class' => BancontactGateway::class ),
		'belfius'         => array( 'gateway_class' => BelfiusGateway::class ),
		'billink'         => array( 'gateway_class' => BillinkGateway::class ),
		'blik'            => array( 'gateway_class' => BlikGateway::class ),
		'creditcard'      => array( 'gateway_class' => CreditCardGateway::class ),
		'eps'             => array( 'gateway_class' => EpsGateway::class ),
		'giftcard'        => array( 'gateway_class' => GiftCardGateway::class ),
		'in3'             => array( 'gateway_class' => In3Gateway::class ),
		'kbc'             => array( 'gateway_class' => KbcGateway::class ),
		'klarnakp'        => array( 'gateway_class' => KlarnaKpGateway::class ),
		'klarnapay'       => array( 'gateway_class' => KlarnaPayGateway::class ),
		'klarnapii'       => array( 'gateway_class' => KlarnaPiiGateway::class ),
		'knaken'          => array( 'gateway_class' => KnakenSettleGateway::class ),
		'mbway'           => array( 'gateway_class' => MbWayGateway::class ),
		'multibanco'      => array( 'gateway_class' => MultibancoGateway::class ),
		'przelewy24'      => array( 'gateway_class' => Przelewy24Gateway::class ),
		'paybybank'       => array( 'gateway_class' => PayByBankGateway::class ),
		'payconiq'        => array( 'gateway_class' => PayconiqGateway::class ),
		'paypal'          => array( 'gateway_class' => PaypalGateway::class ),
		'payperemail'     => array( 'gateway_class' => PayPerEmailGateway::class ),
		'sepadirectdebit' => array( 'gateway_class' => SepaDirectDebitGateway::class ),
		'transfer'        => array( 'gateway_class' => TransferGateway::class ),
	);

	/**
	 * Load necessary gateways and configurations.
	 *
	 * @return PaymentGatewayRegistry
	 */
	public function load(): PaymentGatewayRegistry {
		if ( ! class_exists( 'WC_Payment_Gateways' ) ) {
			return $this;
		}

		$this->loadGateways();
		$this->enableCreditCardsInCheckout();

		return $this;
	}

	public function newGatewayInstance( $method ) {
		if ( is_string( $method ) ) {
			$method = $this->getAllGateways()[ strtolower( $method ) ] ?? null;
		}

		if ( isset( $method['gateway_class'] ) && class_exists( $method['gateway_class'] ) ) {
			$gateway = new $method['gateway_class']();

			if ( method_exists( $gateway, 'handleHooks' ) ) {
				$gateway->handleHooks();
			}

			if ( $gateway->capturable ) {
				new OrderCapture( $gateway );
			}

			return $gateway;
		}

		return null;
	}

	/**
	 * Load all registered gateways.
	 *
	 * @return void
	 */
	protected function loadGateways(): void {
		foreach ( $this->getAllGateways() as $method ) {
			$this->newGatewayInstance( $method );
		}
	}

	/**
	 * Enable individual credit card methods in the checkout, if configured.
	 *
	 * @return void
	 */
	private function enableCreditCardsInCheckout(): void {
		if ( ! get_transient( 'buckaroo_creditcard_updated' ) ) {
			return;
		}

		$gatewayNames = $this->getCreditCardsToShow();

		if ( empty( $gatewayNames ) ) {
			return;
		}

		$creditCardMethods = CreditCardGateway::$cards;

		foreach ( $gatewayNames as $name ) {
			$methodKey = $name . '_creditcard';
			$class     = $creditCardMethods[ $methodKey ]['gateway_class'] ?? null;

			if ( $class && class_exists( $class ) ) {
				$gatewayInstance = new $class();
				if ( method_exists( $gatewayInstance, 'update_option' ) ) {
					$gatewayInstance->update_option( 'enabled', 'yes' );
				}
			}
		}
		delete_transient( 'buckaroo_creditcard_updated' );
	}

	/**
	 * Get all registered gateways, including individual credit card methods.
	 *
	 * @return array
	 */
	protected function getAllGateways(): array {
		$creditCardsToShow = $this->getCreditCardsToShow();
		return array_merge(
			$this->gateways,
			array_filter(
				CreditCardGateway::$cards,
				fn( $key ) => in_array( str_replace( '_creditcard', '', $key ), $creditCardsToShow ),
				ARRAY_FILTER_USE_KEY
			)
		);
	}

	/**
	 * Retrieve the list of credit cards to show in the checkout page.
	 *
	 * @return array
	 */
	public function getCreditCardsToShow(): array {
		$creditSettings = get_option( 'woocommerce_buckaroo_creditcard_settings', null );

		if (
			$creditSettings !== null &&
			isset( $creditSettings['creditcardmethod'], $creditSettings['show_in_checkout'] ) &&
			$creditSettings['creditcardmethod'] === 'encrypt' &&
			is_array( $creditSettings['show_in_checkout'] )
		) {
			return $creditSettings['show_in_checkout'];
		}
		return array();
	}

	/**
	 * Hook to add gateways to WooCommerce's list of payment methods.
	 *
	 * @param array $methods
	 *
	 * @return array
	 */
	public function hookGatewaysToWooCommerce( array $methods ): array {
		foreach ( $this->sortGatewaysAlphabetically( $this->getAllGateways() ) as $method ) {
			$methods[] = $method['gateway_class'];
		}
		return $methods;
	}

	/**
	 * Sort payment gateways alphabetically by their keys.
	 *
	 * @param array $gateways
	 *
	 * @return array
	 */
	protected function sortGatewaysAlphabetically( array $gateways ): array {
		uksort( $gateways, 'strcasecmp' );
		return $gateways;
	}
}
