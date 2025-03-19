<?php

namespace Buckaroo\Woocommerce\Gateways;

use Buckaroo\Woocommerce\Order\OrderArticles;
use Buckaroo\Woocommerce\Order\OrderDetails;
use Buckaroo\Woocommerce\Services\Logger;
use Buckaroo\Woocommerce\Services\Request;
use WC_Order;

class AbstractPaymentProcessor extends AbstractProcessor {

	protected OrderDetails $order_details;
	protected OrderArticles $order_articles;
	protected Request $request;

	public function __construct(
		AbstractPaymentGateway $gateway,
		OrderDetails $order_details,
		OrderArticles $order_articles
	) {
		$this->request        = new Request();
		$this->gateway        = $gateway;
		$this->order_details  = $order_details;
		$this->order_articles = $order_articles;
	}

	public function getAction(): string {
		return 'pay';
	}

	public function getBody(): array {
        $body = array_merge(
            $this->getMethodBody(),
            array(
                'order'                => (string) $this->get_order()->get_id(),
                'invoice'              => $this->get_invoice_number(),
                'amountDebit'          => number_format( (float) $this->get_order()->get_total( 'edit' ), 2, '.', '' ),
                'currency'             => get_woocommerce_currency(),
                'returnURL'            => $this->get_return_url(),
                'cancelURL'            => $this->get_return_url(),
                'pushURL'              => $this->get_push_url(),
                'additionalParameters' => array(
                    'real_order_id' => $this->get_order()->get_id(),
                ),

                'description'          => $this->get_description(),
                'clientIP'             => $this->getIp(),
                'culture'              => $this->determineCulture(),
            )
        );

        Logger::log( __METHOD__ . '|1|', array( $_POST, $body ) );

		return $body;
	}

    protected function getMethodBody(): array {
		return array();
	}

	/**
	 * Get order
	 *
	 * @return WC_Order
	 */
	protected function get_order(): WC_Order {
		return $this->order_details->get_order();
	}

	private function get_invoice_number(): string {
		if ( in_array( $this->gateway->id, array( 'buckaroo_afterpaynew', 'buckaroo_afterpay' ) ) ) {
			return (string) $this->get_order()->get_order_number() . time();
		}
		return (string) $this->get_order()->get_order_number();
	}

	public function get_return_url( $order = null ): string {
		return add_query_arg( 'wc-api', 'WC_Gateway_' . ucfirst( $this->gateway->id ), home_url( '/' ) );
	}

	/**
	 * Get push url
	 *
	 * @return string
	 */
	private function get_push_url(): string {
		return add_query_arg( 'wc-api', 'wc_push_buckaroo', home_url( '/' ) );
	}

	/**
	 * Get the parsed label, we replace the template variables with the values
	 *
	 * @return string
	 */
    public function get_description(): string {
        $order        = $this->get_order();
        $order_number = $order->get_order_number();
        $label        = $this->gateway->get_option( 'transactiondescription', 'Order #' . $order->get_order_number() );

        $label = str_replace( '{order_number}', $order_number, $label );
        $label = str_replace( '{shop_name}', get_bloginfo( 'name' ), $label );

        $products = $order->get_items( 'line_item' );
        if ( count( $products ) ) {
            $label = str_replace( '{product_name}', reset( $products )->get_name(), $label );
        }

        $label = preg_replace( "/\r?\n|\r/", '', $label );
        return mb_substr( $label, 0, 244 );
    }

	/**
	 * Get address component
	 *
	 * @param string $type
	 * @param string $key
	 * @param string $default
	 *
	 * @return mixed
	 */
	protected function getAddress( string $type, string $key, $default = '' ) {
		return $this->order_details->get( $type . '_' . $key, $default );
	}

	/**
	 * Get order articles
	 *
	 * @return array
	 */
	protected function getArticles(): array {
		return $this->order_articles->get_products_for_payment();
	}
}
