<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\Applepay\ApplepayGateway;
use Buckaroo\Woocommerce\Gateways\Express\ExpressRateLimit;
use Buckaroo\Woocommerce\Gateways\Googlepay\GooglepayGateway;

/**
 * The Apple Pay and Google Pay express routes create the order themselves, so
 * they must apply the checkout rules of the available-gateways filter, and
 * accept only real express requests.
 */
class Test_ExpressWalletAvailability extends ExpressWalletTestCase
{
    /**
     * @dataProvider walletGateways
     */
    public function test_disabled_wallet_is_rejected_without_creating_an_order(string $class, string $id): void
    {
        $this->configureWallet($id, ['enabled' => 'no']);

        $this->assertRejectedWithoutOrder(new $class(), self::UNAVAILABLE);
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_wallet_without_credentials_is_rejected_without_creating_an_order(string $class, string $id): void
    {
        $this->configureWallet($id, ['merchant_guid' => '']);

        $this->assertRejectedWithoutOrder(new $class(), self::UNAVAILABLE);
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_unsupported_store_currency_is_rejected_without_creating_an_order(string $class, string $id): void
    {
        $this->configureWallet($id);
        update_option('woocommerce_currency', 'USD');

        $this->assertRejectedWithoutOrder(new $class(), self::UNAVAILABLE);
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_order_above_max_amount_is_rejected_without_keeping_an_order(string $class, string $id): void
    {
        $this->configureWallet($id, ['maxvalue' => '10']);

        $this->assertRejectedWithoutOrder(new $class(), self::UNAVAILABLE);
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_idin_product_without_verification_is_rejected_without_creating_an_order(string $class, string $id): void
    {
        $this->configureWallet($id);
        update_option('woocommerce_buckaroo_mastersettings_settings', ['culture' => 'en-US', 'useidin' => 'test']);

        $this->assertRejectedWithoutOrder(new $class(), self::UNAVAILABLE);
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_verified_idin_customer_can_pay_express(string $class, string $id): void
    {
        $this->configureWallet($id);
        update_option('woocommerce_buckaroo_mastersettings_settings', ['culture' => 'en-US', 'useidin' => 'test']);
        WC()->session->set('buckaroo_idin', 1);
        WC()->session->set('buckaroo_idin_verified_at', time());

        $gateway = new $class();
        $this->postTransaction($this->createSimpleProduct('Verified', '15.00'));

        $this->assertSame(self::PAID, $this->runTransaction($gateway));
        $this->assertSame('pending', wc_get_order($gateway->paidOrderId)->get_status());
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_valid_express_payment_reaches_process_payment(string $class, string $id): void
    {
        $this->configureWallet($id, ['minvalue' => '10', 'maxvalue' => '20']);
        $gateway = new $class();
        $this->postTransaction($this->createSimpleProduct('Allowed', '15.00'));

        $this->assertSame(self::PAID, $this->runTransaction($gateway));

        $order = wc_get_order($gateway->paidOrderId);
        $this->assertInstanceOf(WC_Order::class, $order);
        $this->assertSame($id, $order->get_payment_method());
        $this->assertSame(15.0, (float) $order->get_total());
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_request_without_nonce_is_rejected_without_creating_an_order(string $class, string $id): void
    {
        $this->configureWallet($id);

        $this->assertRejectedWithoutOrder(new $class(), self::EXPIRED, null);
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_request_with_invalid_nonce_is_rejected_without_creating_an_order(string $class, string $id): void
    {
        $this->configureWallet($id);

        $this->assertRejectedWithoutOrder(new $class(), self::EXPIRED, 'invalid');
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_nonce_of_the_other_wallet_is_rejected(string $class, string $id): void
    {
        $this->configureWallet($id);
        $otherAction = $class === Express_Availability_Applepay::class
            ? GooglepayGateway::CREATE_TRANSACTION_NONCE
            : ApplepayGateway::CREATE_TRANSACTION_NONCE;

        $this->assertRejectedWithoutOrder(new $class(), self::EXPIRED, wp_create_nonce($otherAction));
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_get_request_with_a_valid_nonce_is_rejected_without_creating_an_order(string $class, string $id): void
    {
        $this->configureWallet($id);

        $this->assertRejectedWithoutOrder(new $class(), self::EXPIRED, false, 'GET');
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_wallet_without_an_express_placement_is_rejected_without_creating_an_order(string $class, string $id): void
    {
        // An emptied placement list is stored as '' by WooCommerce.
        $this->configureWallet($id, ['express_show_on' => '']);

        $this->assertRejectedWithoutOrder(new $class(), self::UNAVAILABLE);
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_payment_data_without_a_wallet_token_is_rejected_without_creating_an_order(string $class, string $id): void
    {
        $this->configureWallet($id);
        $gateway = new $class();
        $this->postTransaction($this->createSimpleProduct('No token', '15.00'));
        unset($_POST['paymentData']['token']);
        $ordersBefore = $this->orderIds();

        $this->assertSame($this->invalidDataMessage($id), $this->runTransaction($gateway));
        $this->assertNull($gateway->paidOrderId);
        $this->assertSame($ordersBefore, $this->orderIds());
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_session_over_the_rate_limit_is_rejected_without_creating_an_order(string $class, string $id): void
    {
        $this->configureWallet($id);
        $gateway = new $class();
        $this->postTransaction($this->createSimpleProduct('Limited', '15.00'));
        $valid = $_POST;
        unset($_POST['paymentData']['token']);
        for ($i = 0; $i < ExpressRateLimit::SESSION_LIMIT; $i++) {
            $this->assertSame($this->invalidDataMessage($id), $this->runTransaction($gateway));
        }
        $_POST = $valid;
        $ordersBefore = $this->orderIds();

        $this->assertSame(self::TOO_MANY, $this->runTransaction($gateway));
        $this->assertNull($gateway->paidOrderId);
        $this->assertSame($ordersBefore, $this->orderIds());
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_client_address_over_the_rate_limit_is_rejected_across_sessions(string $class, string $id): void
    {
        $this->configureWallet($id);
        $gateway = new $class();
        $this->postTransaction($this->createSimpleProduct('Limited address', '15.00'));
        $valid = $_POST;
        unset($_POST['paymentData']['token']);
        for ($i = 0; $i < ExpressRateLimit::CLIENT_LIMIT; $i++) {
            // A new visitor session from the same address.
            WC()->session->set(ExpressRateLimit::SESSION_KEY, null);
            $this->assertSame($this->invalidDataMessage($id), $this->runTransaction($gateway));
        }
        WC()->session->set(ExpressRateLimit::SESSION_KEY, null);
        $_POST = $valid;

        $this->assertSame(self::TOO_MANY, $this->runTransaction($gateway));
        $this->assertNull($gateway->paidOrderId);
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_requests_rejected_before_the_nonce_check_are_not_counted(string $class, string $id): void
    {
        $this->configureWallet($id);
        $gateway = new $class();
        $this->postTransaction($this->createSimpleProduct('Uncounted', '15.00'));
        for ($i = 0; $i <= ExpressRateLimit::SESSION_LIMIT; $i++) {
            $this->assertSame(self::EXPIRED, $this->runTransaction($gateway, 'invalid'));
        }

        $this->assertSame(self::PAID, $this->runTransaction($gateway));
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_unexpected_payment_error_text_is_not_sent_to_the_shopper(string $class, string $id): void
    {
        $this->configureWallet($id);
        $gateway = new $class();
        $gateway->paymentError = new RuntimeException('Store Key is required.');
        $this->postTransaction($this->createSimpleProduct('Failing', '15.00'));

        $this->assertSame(self::FAILED, $this->runTransaction($gateway));
    }
}
