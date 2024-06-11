<?php
class ApplePayButtons {

	public function loadActions() {
		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'renderButtonOnProductPage' ) );
		add_action( 'woocommerce_after_cart_totals', array( $this, 'renderButtonOnCartPage' ) );
		add_action( 'woocommerce_before_checkout_form', array( $this, 'renderButtonOnCheckoutPage' ) );
	}

	public function renderButtonOnProductPage() {
		global $product;

		if ( $this->buttonIsEnabled( 'product' ) &&
			$this->paymentMethodIsEnabled() &&
			$this->isHttpsConnection() &&
			$product->get_stock_status() === 'instock'
		) {
			echo "<div class='applepay-button-container is-detail-page'><div></div></div>";
		}
	}

	public function renderButtonOnCartPage() {
		if ( $this->buttonIsEnabled( 'cart' ) &&
			$this->paymentMethodIsEnabled() &&
			$this->isHttpsConnection()
		) {
			echo "<div class='applepay-button-container'><div></div></div>";
		}
	}

	public function renderButtonOnCheckoutPage() {
		if ( $this->buttonIsEnabled( 'checkout' ) &&
			$this->paymentMethodIsEnabled() &&
			$this->isHttpsConnection()
		) {
			echo "<div class='applepay-button-container'><div></div></div>";
		}
	}



	private function isHttpsConnection() {
		return isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ? true : false;
	}

	private function buttonIsEnabled( $page ) {
		if ( $settings = get_option( 'woocommerce_buckaroo_applepay_settings' ) ) {
			if ( isset( $settings[ "button_{$page}" ] ) ) {
				return $settings[ "button_{$page}" ] === 'TRUE' ? true : false;
			}
		}
		return false;
	}

	private function paymentMethodIsEnabled() {
		if ( $settings = get_option( 'woocommerce_buckaroo_applepay_settings' ) ) {
			if ( isset( $settings['enabled'] ) ) {
				return $settings['enabled'] === 'yes' ? true : false;
			}
		}
		return false;
	}
}
