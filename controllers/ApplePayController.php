<?php

class ApplePayController {
	public static function getShopInformation() {
		$country_code = preg_replace( '/\:\*/', '', get_option( 'woocommerce_default_country' ) );

		wp_send_json(
			array(
				'store_name'    => get_option( 'blogname' ),
				'country_code'  => $country_code,
				'currency_code' => get_option( 'woocommerce_currency' ),
				'culture_code'  => $country_code,
				'merchant_id'   => get_option( 'woocommerce_buckaroo_applepay_settings' )['merchant_guid'],
			)
		);
	}

	public static function getItemsFromDetailPage() {
		$items = self::createTemporaryCart(
			function () {
				global $woocommerce;

				$cart = $woocommerce->cart;

				$products = self::getProductsFromCart( $cart );

				$product = reset( $products );

				$coupons = array_map(
					function ( $coupon ) use ( $cart ) {
						$price = $cart->get_coupon_discount_amount( $coupon->get_code(), $cart->display_cart_ex_tax );
						return array(
							'type'       => 'coupon',
							'id'         => $coupon->get_id(),
							'name'       => "Coupon: {$coupon->get_code()}",
							'price'      => "-{$price}",
							'quantity'   => 1,
							'attributes' => array(),
						);
					},
					$cart->get_coupons()
				);

				$fees = array_map(
					function ( $fee ) {
						return array(
							'type'       => 'fee',
							'id'         => $fee->id,
							'name'       => $fee->name,
							'price'      => $fee->amount,
							'quantity'   => 1,
							'attributes' => array(
								'taxable' => $fee->taxable,
							),
						);
					},
					$cart->get_fees()
				);
				return array_merge( array( $product ), $coupons, $fees );
			}
		);

		wp_send_json( array_values( $items ) );
	}

	public static function getItemsFromCart() {
		global $woocommerce;

		$cart = $woocommerce->cart;

		$products = self::getProductsFromCart( $cart );

		$coupons = array_map(
			function ( $coupon ) use ( $cart ) {
				$price = $cart->get_coupon_discount_amount( $coupon->get_code(), $cart->display_cart_ex_tax );
				return array(
					'type'       => 'coupon',
					'id'         => $coupon->get_id(),
					'name'       => "Coupon: {$coupon->get_code()}",
					'price'      => "-{$price}",
					'quantity'   => 1,
					'attributes' => array(),
				);
			},
			$cart->get_coupons()
		);

		self::calculate_fee( $cart );

		$fees = array_map(
			function ( $fee ) {
				return array(
					'type'       => 'fee',
					'id'         => $fee->id,
					'name'       => $fee->name,
					'price'      => $fee->amount,
					'quantity'   => 1,
					'attributes' => array(
						'taxable' => $fee->taxable,
					),
				);
			},
			$cart->get_fees()
		);

		$items = array_merge( $products, $coupons, $fees );

		wp_send_json( array_values( $items ) );
	}

	private static function getProductsFromCart( $cart ) {
		$products = array_map(
			function ( $product ) {

				$id = $product['variation_id'] !== 0
				? $product['variation_id']
				: $product['product_id'];
				return array(
					'type'       => 'product',
					'id'         => absint( $id ),
					'name'       => wc_get_product( $id )->get_name(),
					'price'      => $product['line_total'] + $product['line_tax'],
					'quantity'   => $product['quantity'],
					'attributes' => array(),
				);
			},
			$cart->get_cart_contents()
		);

		return $products;
	}

	public static function getShippingMethods() {
		function wcMethods() {
			global $woocommerce;

			$cart = $woocommerce->cart;

			$country_code = '';
			if ( isset( $_GET['country_code'] ) && is_string( $_GET['country_code'] ) ) {
				$country_code = strtoupper( sanitize_text_field( $_GET['country_code'] ) );
			}

			$customer = $woocommerce->customer;
			$customer->set_shipping_country( $country_code );

			$packages = $woocommerce->cart->get_shipping_packages();

			return $woocommerce->shipping
				->calculate_shipping_for_package( current( $packages ) )['rates'];
		}
		if ( isset( $_GET['product_id'] ) && is_numeric( $_GET['product_id'] ) ) {
			$wc_methods = self::createTemporaryCart(
				function () {
					return wcMethods();
				}
			);
		} else {
			$wc_methods = wcMethods(); }

		$shipping_methods = array_map(
			function ( $wc_method ) {
				return array(
					'identifier' => $wc_method->get_id(),
					'detail'     => '',
					'label'      => $wc_method->get_label(),
					'amount'     => (float) number_format( $wc_method->get_cost() + $wc_method->get_shipping_tax(), 2 ),
				);
			},
			$wc_methods
		);

		wp_send_json( array_values( $shipping_methods ) );
	}



	/**
	 * Some methods need to have a temporary cart if a user is on the product detail page
	 * We empty the cart and put the current shown product + quantity in the cart and reapply the coupons
	 * to determine the discounts, free shipping and other rules based on that cart
	 *
	 * @return array
	 */
	private static function createTemporaryCart( $callback ) {
		if ( ! ( isset( $_GET['product_id'] ) && is_numeric( $_GET['product_id'] ) ) ) {
			throw new \Exception( 'Invalid product_id' );
		}

		if ( isset( $_GET['variation_id'] ) && ! is_numeric( $_GET['variation_id'] ) ) {
			throw new \Exception( 'Invalid variation_id' );
		}

		if ( ! ( isset( $_GET['quantity'] ) && is_numeric( $_GET['quantity'] ) && $_GET['quantity'] > 0 ) ) {
			throw new \Exception( 'Invalid quantity' );
		}

		global $woocommerce;

		/** @var WC_Cart */
		$cart = $woocommerce->cart;

		$current_shown_product = array(
			'product_id'   => absint( $_GET['product_id'] ),
			'variation_id' => absint( $_GET['variation_id'] ),
			'quantity'     => (int) $_GET['quantity'],
		);

		$original_cart_products = array_map(
			function ( $product ) {
				return array(
					'product_id'   => $product['product_id'],
					'variation_id' => $product['variation_id'],
					'quantity'     => $product['quantity'],
				);
			},
			$cart->get_cart_contents()
		);

		$original_applied_coupons = array_map(
			function ( $coupon ) {
				return array(
					'coupon_id' => $coupon->get_id(),
					'code'      => $coupon->get_code(),
				);
			},
			$cart->get_coupons()
		);

		$cart->empty_cart();

		if ( $current_shown_product['product_id'] != $current_shown_product['variation_id'] ) {
			$cart->add_to_cart(
				$current_shown_product['product_id'],
				$current_shown_product['quantity'],
				$current_shown_product['variation_id']
			);
		} else {
			$cart->add_to_cart(
				$current_shown_product['product_id'],
				$current_shown_product['quantity'],
			);
		}

		foreach ( $original_applied_coupons as $original_applied_coupon ) {
			$cart->apply_coupon( $original_applied_coupon['code'] );
		}

		self::calculate_fee( $cart );

		$temporary_cart_result = call_user_func( $callback );

		// restore previous cart
		$cart->empty_cart();

		foreach ( $original_cart_products as $original_product ) {
			$cart->add_to_cart(
				$original_product['product_id'],
				$original_product['quantity'],
				$original_product['variation_id']
			);
		}

		foreach ( $original_applied_coupons as $original_applied_coupon ) {
			$cart->apply_coupon( $original_applied_coupon['code'] );
		}

		wc_clear_notices();

		return $temporary_cart_result;
	}
	public static function calculate_fee( $cart ) {
		$cart->calculate_totals();

		$feed_settings = self::get_extra_feed_settings();
		do_action(
			'buckaroo_cart_calculate_fees',
			$cart,
			$feed_settings['extrachargeamount'],
			$feed_settings['feetax']
		);
	}
	private static function get_extra_feed_settings() {
		$settings = get_option( 'woocommerce_buckaroo_applepay_settings' );
		return array(
			'extrachargeamount' => isset( $settings['extrachargeamount'] ) ? $settings['extrachargeamount'] : 0,
			'feetax'            => isset( $settings['feetax'] ) ? $settings['feetax'] : '',
		);
	}
}
