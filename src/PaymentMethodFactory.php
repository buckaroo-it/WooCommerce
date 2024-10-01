<?php

namespace WC_Buckaroo\WooCommerce;

class PaymentMethodFactory
{
    protected const HANDLERS = [
        'iDeal' => ['classname' => PaymentMethods\Ideal::class],
        'Belfius' => ['classname' => PaymentMethods\Belfius::class],
        'Billink' => ['classname' => PaymentMethods\Billink::class],
        'Bank Transfer' => ['classname' => PaymentMethods\Transfer::class],
        'Bancontact / MisterCash' => ['classname' => PaymentMethods\Bancontact::class],
        'Applepay' => ['classname' => PaymentMethods\ApplePay::class],
        'AfterPayNew' => ['classname' => PaymentMethods\AfterPayNew::class],
        'AfterPay' => ['classname' => PaymentMethods\AfterPay::class],
        'Creditcards' => ['classname' => PaymentMethods\CreditCard::class],
        'EPS' => ['classname' => PaymentMethods\EPS::class],
        'Giftcards' => ['classname' => PaymentMethods\GiftCard::class],
        'Giropay' => ['classname' => PaymentMethods\Giropay::class],
        'In3' => ['classname' => PaymentMethods\In3::class],
        'KBC' => ['classname' => PaymentMethods\KBC::class],
        'KlarnaPay' => ['classname' => PaymentMethods\KlarnaPay::class],
        'KlarnaPII' => ['classname' => PaymentMethods\KlarnaPII::class],
        'KlarnaKp' => ['classname' => PaymentMethods\KlarnaKp::class],
        'KnakenSettle' => ['classname' => PaymentMethods\KnakenSettle::class],
        'P24' => ['classname' => PaymentMethods\P24::class],
        'Payconiq' => ['classname' => PaymentMethods\Payconiq::class],
        'PayPal' => ['classname' => PaymentMethods\PayPal::class],
        'PayPerEmail' => ['classname' => PaymentMethods\PayPerEmail::class],
        'SepaDirectDebit' => ['classname' => PaymentMethods\SepaDirectDebit::class],
        'Sofortbanking' => ['classname' => PaymentMethods\Sofortbanking::class],
        'PayByBank' => ['classname' => PaymentMethods\PayByBank::class],
        'Multibanco' => ['classname' => PaymentMethods\Multibanco::class],
        'MBWay' => ['classname' => PaymentMethods\MBWay::class],
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
            $class = $this->get_creditcard_methods()[$name . '_creditcard']['classname'] ?? null;

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
            $methods[] = new $method['classname'];
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
            if (class_exists($method['classname'])) {
                new $method['classname']();
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
                $creditcardMethods[$creditcard . '_creditcard'] = ['classname' => $class];
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
                'classname' => PaymentMethods\Exodus::class,
            );
        }
    }
}