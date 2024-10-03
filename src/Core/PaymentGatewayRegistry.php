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
use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaGateway;
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
use Buckaroo\Woocommerce\Gateways\Sofort\SofortGateway;
use Buckaroo\Woocommerce\Gateways\Transfer\TransferGateway;

class PaymentGatewayRegistry
{
    protected array $gateways = [
        'ideal' => ['gateway_class' => IdealGateway::class],
        'afterpay' => ['gateway_class' => AfterpayOldGateway::class],
        'afterpay_new' => ['gateway_class' => AfterpayNewGateway::class],
        'applepay' => ['gateway_class' => ApplepayGateway::class],
        'bancontact' => ['gateway_class' => BancontactGateway::class],
        'belfius' => ['gateway_class' => BelfiusGateway::class],
        'billink' => ['gateway_class' => BillinkGateway::class],
        'blik' => ['gateway_class' => BlikGateway::class],
        'creditcard' => ['gateway_class' => CreditCardGateway::class],
        'eps' => ['gateway_class' => EpsGateway::class],
        'giftcard' => ['gateway_class' => GiftCardGateway::class],
        'in3' => ['gateway_class' => In3Gateway::class],
        'kbc' => ['gateway_class' => KbcGateway::class],
        'klarna' => ['gateway_class' => KlarnaGateway::class],
        'klarnakp' => ['gateway_class' => KlarnaKpGateway::class],
        'klarnapay' => ['gateway_class' => KlarnaPayGateway::class],
        'klarnapii' => ['gateway_class' => KlarnaPiiGateway::class],
        'knaken' => ['gateway_class' => KnakenSettleGateway::class],
        'mbway' => ['gateway_class' => MbWayGateway::class],
        'multibanco' => ['gateway_class' => MultibancoGateway::class],
        'przelewy24' => ['gateway_class' => Przelewy24Gateway::class],
        'paybybank' => ['gateway_class' => PayByBankGateway::class],
        'payconiq' => ['gateway_class' => PayconiqGateway::class],
        'paypal' => ['gateway_class' => PaypalGateway::class],
        'payperemail' => ['gateway_class' => PayPerEmailGateway::class],
        'sepadirectdebit' => ['gateway_class' => SepaDirectDebitGateway::class],
        'sofort' => ['gateway_class' => SofortGateway::class],
        'transfer' => ['gateway_class' => TransferGateway::class],
    ];

    /**
     * Load necesary
     *
     * @return PaymentGatewayRegistry
     */
    public function load()
    {
        $this->add_exodus();
        $this->load_before();
        $this->load_gateways();
        $this->load_after();
        $this->enable_creditcards_in_checkout();

        return $this;
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
                'filename' => 'buckaroo-exodus.php',
                'gateway_class' => 'WC_Gateway_Buckaroo_Exodus',
            );
        }
    }

    /**
     * Load before the gateways
     *
     * @return void
     */
    protected function load_before()
    {
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
        return array_merge($this->gateways, $this->get_creditcard_methods());
    }

    /**
     * Get credicard payment methods
     *
     * @return array
     */
    protected function get_creditcard_methods()
    {
        $creditcardMethods = array();

        foreach ($this->get_creditcards_to_show() as $creditcard) {
            if (strlen(trim($creditcard)) !== 0 && class_exists($class = 'Buckaroo\Woocommerce\Gateways\CreditCard\Cards\\' . ucfirst($creditcard) . 'Gateway')) {
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
        return array();
    }

    /**
     * Load after the gateways
     *
     * @return void
     */
    protected function load_after(): void
    {
        //
    }

    /**
     * Enable credicard method when set to be shown individually in checkout page
     *
     * @return void
     */
    public function enable_creditcards_in_checkout(): void
    {
        if (!get_transient('buckaroo_credicard_updated')) {
            return;
        }

        $gatewayNames = $this->get_creditcards_to_show();

        if (!is_array($gatewayNames)) {
            return;
        }

        foreach ($gatewayNames as $name) {
            $class = 'WC_Gateway_Buckaroo_' . ucfirst($name);
            if (class_exists($class)) {
                var_dump(class_exists($class));
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
    public function hook_gateways_to_woocommerce($methods): array
    {
        foreach ($this->sort_gateways_alfa($this->get_all_gateways()) as $method) {
            $methods[] = $method['gateway_class'];
        }
        return $methods;
    }

    /**
     * Sort payment gateway alphabetically by name
     *
     * @param array $gateway
     *
     * @return array
     */
    protected function sort_gateways_alfa(array $gateways): array
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
}