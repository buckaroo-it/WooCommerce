<?php

namespace WC_Buckaroo\WooCommerce;

use Buckaroo_Http_Request;
use WC_Buckaroo\WooCommerce\Payment\OrderArticles;
use WC_Buckaroo\WooCommerce\Payment\OrderDetails;
use WC_Buckaroo\WooCommerce\PaymentMethods\PaymentGatewayHandler;
use WC_Buckaroo\WooCommerce\PaymentMethods\PaymentProcessorHandler;
use WC_Buckaroo\WooCommerce\SDK\Buckaroo_Sdk_Payload_Interface;
use WC_Order;

class PaymentMethodFactory
{
    protected const HANDLERS = [
        'Alipay' => ['processor_class' => PaymentMethods\Alipay\AlipayProcessor::class],
        'iDeal' => ['gateway_class' => PaymentMethods\Ideal\IdealGateway::class, 'processor_class' => PaymentMethods\Ideal\IdealProcessor::class],
        'Belfius' => ['gateway_class' => PaymentMethods\Belfius\BelfiusGateway::class],
        'Billink' => ['gateway_class' => PaymentMethods\Billink\BillinkGateway::class, 'processor_class' => PaymentMethods\Billink\BillinkProcessor::class],
        'transfer' => ['gateway_class' => PaymentMethods\Transfer\TransferGateway::class, 'processor_class' => PaymentMethods\Transfer\TransferProcessor::class],
        'Bancontact / MisterCash' => ['gateway_class' => PaymentMethods\Bancontact\BancontactGateway::class],
        'Applepay' => ['gateway_class' => PaymentMethods\Applepay\ApplePayGateway::class, 'processor_class' => PaymentMethods\Applepay\ApplePayProcessor::class],
        'AfterPayNew' => ['gateway_class' => PaymentMethods\Afterpay\AfterpayNewGateway::class, 'processor_class' => PaymentMethods\Afterpay\AfterpayNewProcessor::class],
        'AfterPay' => ['gateway_class' => PaymentMethods\Afterpay\AfterpayOldGateway::class, 'processor_class' => PaymentMethods\Afterpay\AfterpayOldProcessor::class],
        'Creditcards' => ['gateway_class' => PaymentMethods\CreditCards\CreditCardGateway::class, 'processor_class' => PaymentMethods\CreditCards\CreditCardProcessor::class],
        'EPS' => ['gateway_class' => PaymentMethods\Eps\EpsGateway::class],
        'Giftcards' => ['gateway_class' => PaymentMethods\GiftCard\GiftCardGateway::class, 'processor_class' => PaymentMethods\GiftCard\GiftCardProcessor::class],
        'Giropay' => ['gateway_class' => PaymentMethods\Giropay\GiropayGateway::class],
        'In3' => ['gateway_class' => PaymentMethods\In3\In3Gateway::class, 'processor_class' => PaymentMethods\In3\In3Processor::class],
        'KBC' => ['gateway_class' => PaymentMethods\Kbc\KbcGateway::class],
        'KlarnaPay' => ['gateway_class' => PaymentMethods\Klarna\KlarnaPayGateway::class, 'processor_class' => PaymentMethods\Klarna\KlarnaProcessor::class],
        'KlarnaPII' => ['gateway_class' => PaymentMethods\Klarna\KlarnaPiiGateway::class],
        'KlarnaKp' => ['gateway_class' => PaymentMethods\Klarna\KlarnaKpGateway::class],
        'KnakenSettle' => ['gateway_class' => PaymentMethods\KnakenSettle\KnakenSettleGateway::class],
        'P24' => ['gateway_class' => PaymentMethods\P24\P24Gateway::class, 'processor_class' => PaymentMethods\P24\P24Processor::class],
        'Payconiq' => ['gateway_class' => PaymentMethods\Payconiq\PayconiqGateway::class],
        'PayPal' => ['gateway_class' => PaymentMethods\Paypal\PaypalGateway::class, 'processor_class' => PaymentMethods\Paypal\PaypalProcessor::class],
        'PayPerEmail' => ['gateway_class' => PaymentMethods\PayPerEmail\PayPerEmailGateway::class, 'processor_class' => PaymentMethods\PayPerEmail\PayPerEmailProcessor::class],
        'SepaDirectDebit' => ['gateway_class' => PaymentMethods\SepaDirectDebit\SepaDirectDebitGateway::class, 'processor_class' => PaymentMethods\SepaDirectDebit\SepaDirectDebitProcessor::class],
        'Sofortbanking' => ['gateway_class' => PaymentMethods\Sofort\SofortGateway::class],
        'PayByBank' => ['gateway_class' => PaymentMethods\PayByBank\PayByBankGateway::class, 'processor_class' => PaymentMethods\PayByBank\PayByBankProcessor::class],
        'Multibanco' => ['gateway_class' => PaymentMethods\Multibanco\MultibancoGateway::class],
        'MBWay' => ['gateway_class' => PaymentMethods\MbWay\MbWayGateway::class],
        'Trustly' => ['processor_class' => PaymentMethods\Trustly\TrustlyProcessor::class],
        'WeChatPay' => ['processor_class' => PaymentMethods\WeChatPay\WeChatPayProcessor::class],
    ];

    public function load()
    {
        $this->add_exodus();
        $this->load_before();
        $this->load_gateways();
        $this->load_after();
        $this->enable_creditcards_in_checkout();
    }

    /**
     * Enable credicard method when set to be shown individually in checkout page
     *
     * @return void
     */
    public function enable_creditcards_in_checkout()
    {
        if (!get_transient('buckaroo_credicard_updated')) {
            return;
        }

        $gatewayNames = $this->get_creditcards_to_show();

        if (!is_array($gatewayNames)) {
            return;
        }

        foreach ($gatewayNames as $name) {
            $class = $this->get_creditcard_methods()[$name . '_creditcard']['gateway_class'] ?? null;

            if (class_exists($class)) {
                (new $class())->update_option('enabled', 'yes');
            }
        }
        delete_transient('buckaroo_credicard_updated');
    }


    /**
     * Hook function for `woocommerce_payment_gateways` hook
     *
     * @param array $methods
     *
     * @return array
     */
    public function hook_gateways_to_woocommerce($methods)
    {
        foreach ($this->sort_gateways_alfa($this->get_all_gateways()) as $method) {
            if (class_exists($method['gateway_class'] ?? null)) {
                $methods[] = new $method['gateway_class'];
            }
        }

        return $methods;
    }

    /**
     * load all gateways
     *
     * @return void
     */
    protected function load_gateways()
    {
        foreach ($this->get_all_gateways() as $method) {
            if (class_exists($method['gateway_class'] ?? null)) {
                new $method['gateway_class']();
            }
        }
    }

    /**
     * Get all gateways
     *
     * @return array
     */
    protected function get_all_gateways()
    {
        return array_merge(self::HANDLERS, $this->get_creditcard_methods());
    }

    /**
     * Load before the gateways
     *
     * @return void
     */
    protected function load_before()
    {
        //
    }

    /**
     * Load after the gateways
     *
     * @return void
     */
    protected function load_after()
    {
        //
    }

    /**
     * Get credicard payment methods
     *
     * @return array
     */
    protected function get_creditcard_methods()
    {
        $creditcardMethods = [];

        foreach ($this->get_creditcards_to_show() as $creditcard) {
            if (strlen(trim($creditcard)) !== 0 && class_exists($class = 'WC_Buckaroo\WooCommerce\PaymentMethods\CreditCards\\' . ucfirst($creditcard))) {
                $creditcardMethods[$creditcard . '_creditcard'] = ['gateway_class' => $class];
            }
        }
        return $creditcardMethods;
    }

    /**
     * Get creditcards to show in checkout page
     *
     * @return array
     */
    public function get_creditcards_to_show()
    {
        $credit_settings = get_option('woocommerce_buckaroo_creditcard_settings', null);

        if (
            $credit_settings !== null &&
            isset($credit_settings['creditcardmethod']) &&
            $credit_settings['creditcardmethod'] === 'encrypt' &&
            isset($credit_settings['show_in_checkout']) &&
            is_array($credit_settings['show_in_checkout'])
        ) {
            return $credit_settings['show_in_checkout'];
        }
        return [];
    }

    /**
     * Sort payment gateway alphabetically by name
     *
     * @param array $gateway
     *
     * @return array
     */
    protected function sort_gateways_alfa($gateways)
    {
        uksort(
            $gateways,
            function ($a, $b) {
                return strcmp(
                    strtolower($a),
                    strtoLower($b)
                );
            }
        );
        return $gateways;
    }

    /**
     * Load exodus class
     *
     * @return void
     */
    private function add_exodus()
    {
        if (file_exists(dirname(BK_PLUGIN_FILE) . '/buckaroo-exodus.php') && !get_option('woocommerce_buckaroo_exodus')) {
            $this->methods['Exodus Script'] = array(
                'gateway_class' => PaymentMethods\Exodus::class,
            );
        }
    }

    public static function get_payment(PaymentGatewayHandler $gateway, int $order_id): Buckaroo_Sdk_Payload_Interface
    {
        $order_details = new OrderDetails(new WC_Order($order_id));
        $class = PaymentProcessorHandler::class;

        $code = strtolower($gateway->get_sdk_code());
        ray($code);
        if (array_key_exists($code, self::HANDLERS)) {
            $class = self::HANDLERS[$code]['processor_class'];
        }

        return new $class(
            $gateway,
            new Buckaroo_Http_Request(),
            $order_details,
            new OrderArticles($order_details, $gateway)
        );
    }
}