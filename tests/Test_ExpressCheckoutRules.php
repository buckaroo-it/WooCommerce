<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\Applepay\ApplepayController;
use Buckaroo\Woocommerce\Gateways\Express\ExpressCheckoutRules;
use Buckaroo\Woocommerce\Gateways\Express\ExpressRateLimit;
use Buckaroo\Woocommerce\Gateways\ExpressPaymentManager;
use Buckaroo\Woocommerce\Gateways\Googlepay\GooglepayController;
use Buckaroo\Woocommerce\Gateways\Paypal\PaypalGateway;
use Buckaroo\Woocommerce\Hooks\InitGateways;

/**
 * Express payments must apply the rules the WooCommerce checkout applies to
 * the same cart: coupon limits, checkout settings and validation hooks.
 */
class Test_ExpressCheckoutRules extends ExpressWalletTestCase
{
    private const LOGIN_REQUIRED = 'You must be logged in to checkout.';

    /**
     * @dataProvider walletGateways
     */
    public function test_logged_in_shopper_cannot_reuse_a_one_per_user_coupon(string $class, string $id): void
    {
        $this->configureWallet($id);
        $userId = $this->loginCustomer('account@example.com');
        $productId = $this->cartWithCoupon($this->createCoupon('once-per-user', ['usage_limit_per_user' => 1]));

        $first = new $class();
        // The wallet e-mail is not the account e-mail, as with most Apple IDs.
        $this->postTransaction($productId, '14.00', 'wallet@example.com');
        $this->assertSame(self::PAID, $this->runTransaction($first));
        $order = wc_get_order($first->paidOrderId);
        $this->assertSame($userId, $order->get_customer_id());
        $order->update_status('processing');

        $second = new $class();
        $message = $this->runTransaction($second);

        $this->assertStringContainsString('once-per-user', $message);
        $this->assertNull($second->paidOrderId);
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_guest_cannot_reuse_a_one_per_email_coupon(string $class, string $id): void
    {
        $this->configureWallet($id);
        $productId = $this->cartWithCoupon($this->createCoupon('once-per-email', ['usage_limit_per_user' => 1]));

        $first = new $class();
        $this->postTransaction($productId, '14.00', 'guest@example.com');
        $this->assertSame(self::PAID, $this->runTransaction($first));
        $order = wc_get_order($first->paidOrderId);
        $this->assertSame(0, $order->get_customer_id());
        $order->update_status('processing');

        $second = new $class();
        $message = $this->runTransaction($second);

        $this->assertStringContainsStringIgnoringCase('usage limit', $message);
        $this->assertNull($second->paidOrderId);
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_coupon_restricted_to_another_email_is_rejected(string $class, string $id): void
    {
        $this->configureWallet($id);
        WC()->customer->set_billing_email('allowed@example.com');
        $productId = $this->cartWithCoupon(
            $this->createCoupon('allowed-only', ['email_restrictions' => ['allowed@example.com']])
        );
        $gateway = new $class();
        $this->postTransaction($productId, '14.00', 'other@example.com');

        $message = $this->runTransaction($gateway);

        $this->assertStringContainsString('allowed-only', $message);
        $this->assertNull($gateway->paidOrderId);
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_coupon_restricted_to_the_wallet_email_is_accepted(string $class, string $id): void
    {
        $this->configureWallet($id);
        WC()->customer->set_billing_email('allowed@example.com');
        $productId = $this->cartWithCoupon(
            $this->createCoupon('allowed-wallet', ['email_restrictions' => ['allowed@example.com']])
        );
        $gateway = new $class();
        $this->postTransaction($productId, '14.00', 'allowed@example.com');

        $this->assertSame(self::PAID, $this->runTransaction($gateway));
        $this->assertSame(14.0, (float) wc_get_order($gateway->paidOrderId)->get_total());
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_coupon_of_a_pending_express_order_is_held(string $class, string $id): void
    {
        $this->configureWallet($id);
        $productId = $this->cartWithCoupon($this->createCoupon('single-use', ['usage_limit' => 1]));

        $first = new $class();
        $this->postTransaction($productId, '14.00', 'first@example.com');
        $this->assertSame(self::PAID, $this->runTransaction($first));

        $second = new $class();
        $this->postTransaction($productId, '14.00', 'second@example.com');
        $message = $this->runTransaction($second);

        $this->assertStringContainsString('single-use', $message);
        $this->assertNull($second->paidOrderId);
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_payment_that_could_not_start_releases_its_coupon_hold(string $class, string $id): void
    {
        $this->configureWallet($id);
        $productId = $this->cartWithCoupon($this->createCoupon('retry-after-error', ['usage_limit' => 1]));
        $this->postTransaction($productId, '14.00');
        $failing = new $class();
        $failing->paymentError = new RuntimeException('Buckaroo is unreachable.');
        $this->assertSame(self::FAILED, $this->runTransaction($failing));

        $gateway = new $class();

        $this->assertSame(self::PAID, $this->runTransaction($gateway));
    }

    public function test_order_rejected_after_its_coupon_hold_releases_it(): void
    {
        $this->configureWallet('buckaroo_applepay');
        $productId = $this->cartWithCoupon($this->createCoupon('retry-after-drift', ['usage_limit' => 1]));
        // The wallet approved an amount the order does not total.
        $this->postTransaction($productId, '13.00');
        $this->assertSame(self::FAILED, $this->runTransaction(new Express_Availability_Applepay()));

        $gateway = new Express_Availability_Applepay();
        $this->postTransaction($productId, '14.00');

        $this->assertSame(self::PAID, $this->runTransaction($gateway));
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_rejected_coupon_leaves_the_live_cart_and_notices_unchanged(string $class, string $id): void
    {
        $this->configureWallet($id);
        WC()->customer->set_billing_email('allowed@example.com');
        $productId = $this->cartWithCoupon(
            $this->createCoupon('keep-in-cart', ['email_restrictions' => ['allowed@example.com']])
        );
        wc_add_notice('Keep this notice', 'notice');
        $coupons = WC()->cart->get_applied_coupons();
        $contents = serialize(WC()->cart->get_cart_contents());
        $notices = wc_get_notices();
        $this->postTransaction($productId, '14.00', 'other@example.com');

        $liveCalculations = $this->countLiveCartCalculations(function () use ($class): void {
            $this->assertStringContainsString('keep-in-cart', $this->runTransaction(new $class()));
        });

        $this->assertSame(0, $liveCalculations, 'The live cart must not be recalculated.');
        $this->assertSame($coupons, WC()->cart->get_applied_coupons());
        $this->assertSame($contents, serialize(WC()->cart->get_cart_contents()));
        $this->assertSame($notices, wc_get_notices());
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_live_cart_coupon_that_does_not_fit_the_product_page_item_is_left_out(string $class, string $id): void
    {
        $this->configureWallet($id);
        $cartProductId = $this->createSimpleProduct('In the cart', '15.00');
        $coupon = $this->createCoupon('cart-product-only', ['product_ids' => [$cartProductId]]);
        $this->assertIsString(WC()->cart->add_to_cart($cartProductId, 1));
        $this->assertTrue(WC()->cart->apply_coupon($coupon->get_code()));
        $gateway = new $class();
        // Bought from its product page: the express cart holds only this product.
        $this->postTransaction($this->createSimpleProduct('Bought now', '20.00'), '20.00');
        $removed = 0;
        $countRemoval = static function () use (&$removed): void {
            $removed++;
        };
        add_action('woocommerce_removed_coupon', $countRemoval);

        try {
            $liveCalculations = $this->countLiveCartCalculations(function () use ($gateway): void {
                $this->assertSame(self::PAID, $this->runTransaction($gateway));
            });
        } finally {
            remove_action('woocommerce_removed_coupon', $countRemoval);
        }

        $order = wc_get_order($gateway->paidOrderId);
        $this->assertSame([], $order->get_coupon_codes());
        $this->assertSame(20.0, (float) $order->get_total());
        $this->assertSame([$coupon->get_code()], WC()->cart->get_applied_coupons());
        $this->assertSame(0, $liveCalculations, 'The live cart must not be recalculated.');
        $this->assertSame(0, $removed, 'The shopper removed no coupon.');
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_express_payment_leaves_the_order_awaiting_payment_alone(string $class, string $id): void
    {
        $this->configureWallet($id);
        $failed = wc_create_order();
        $failed->update_status('failed');
        WC()->session->set('order_awaiting_payment', $failed->get_id());
        $this->postTransaction($this->createSimpleProduct('Second try', '15.00'));

        try {
            $this->assertSame(self::PAID, $this->runTransaction(new $class()));
        } finally {
            WC()->session->set('order_awaiting_payment', null);
        }

        $this->assertSame('failed', wc_get_order($failed->get_id())->get_status());
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_guest_is_rejected_when_the_shop_requires_an_account(string $class, string $id): void
    {
        $this->configureWallet($id);
        update_option('woocommerce_enable_guest_checkout', 'no');

        $this->assertRejectedWithoutOrder(new $class(), self::LOGIN_REQUIRED);
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_logged_in_shopper_pays_when_the_shop_requires_an_account(string $class, string $id): void
    {
        $this->configureWallet($id);
        update_option('woocommerce_enable_guest_checkout', 'no');
        $this->loginCustomer('account@example.com');
        $gateway = new $class();
        $this->postTransaction($this->createSimpleProduct('Account product', '15.00'));

        $this->assertSame(self::PAID, $this->runTransaction($gateway));
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_rejected_attempt_creates_no_order_at_all(string $class, string $id): void
    {
        $this->configureWallet($id);
        update_option('woocommerce_enable_guest_checkout', 'no');
        $created = 0;
        $count = static function () use (&$created): void {
            $created++;
        };
        add_action('woocommerce_new_order', $count);
        $this->postTransaction($this->createSimpleProduct('Never ordered', '15.00'));

        try {
            $this->assertSame(self::LOGIN_REQUIRED, $this->runTransaction(new $class()));
        } finally {
            remove_action('woocommerce_new_order', $count);
        }

        $this->assertSame(0, $created, 'New-order hooks must not fire for a rejected attempt.');
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_wallet_removed_by_a_gateway_filter_is_rejected(string $class, string $id): void
    {
        $this->configureWallet($id);
        $remove = static function ($gateways) use ($id) {
            unset($gateways[$id]);

            return $gateways;
        };
        add_filter('woocommerce_available_payment_gateways', $remove, 20);

        try {
            $this->assertRejectedWithoutOrder(new $class(), self::UNAVAILABLE);
        } finally {
            remove_filter('woocommerce_available_payment_gateways', $remove, 20);
        }
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_wallet_shown_only_as_an_express_button_still_pays(string $class, string $id): void
    {
        $this->configureWallet($id, ['checkout_method' => 'FALSE']);
        $gateway = new $class();
        $this->postTransaction($this->createSimpleProduct('Express only', '15.00'));

        $this->assertSame(self::PAID, $this->runTransaction($gateway));
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_product_held_by_another_order_is_rejected_when_the_live_cart_is_reused(string $class, string $id): void
    {
        $this->configureWallet($id);
        $productId = $this->createSimpleProduct('Last one', '15.00');
        $product = wc_get_product($productId);
        $product->set_manage_stock(true);
        $product->set_stock_quantity(1);
        $product->save();
        $this->assertIsString(WC()->cart->add_to_cart($productId, 1));
        // Another shopper's unpaid order holds the last unit.
        $other = wc_create_order();
        $other->add_product(wc_get_product($productId), 1);
        $other->save();
        wc_reserve_stock_for_order($other);
        $gateway = new $class();
        $this->postTransaction($productId);

        $message = $this->runTransaction($gateway);

        $this->assertStringContainsStringIgnoringCase('stock', $message);
        $this->assertNull($gateway->paidOrderId);
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_unpaid_express_order_reserves_the_last_unit_from_other_shoppers(string $class, string $id): void
    {
        $this->configureWallet($id);
        $productId = $this->createStockedProduct('Last unit', 1);

        $first = new $class();
        $this->postTransaction($productId);
        $this->assertSame(self::PAID, $this->runTransaction($first));
        $this->assertSame(1, (int) wc_get_held_stock_quantity(wc_get_product($productId)));

        // Another shopper's session, while the first order waits for its payment.
        $this->startOtherSession();
        $second = new $class();
        $message = $this->runTransaction($second);

        $this->assertStringContainsStringIgnoringCase('stock', $message);
        $this->assertNull($second->paidOrderId);
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_shopper_can_retry_while_their_own_express_order_is_unpaid(string $class, string $id): void
    {
        $this->configureWallet($id);
        $productId = $this->createStockedProduct('Retried unit', 1);
        $first = new $class();
        $this->postTransaction($productId);
        $this->assertSame(self::PAID, $this->runTransaction($first));

        $retry = new $class();

        $this->assertSame(self::PAID, $this->runTransaction($retry));
        $this->assertSame(1, (int) wc_get_held_stock_quantity(wc_get_product($productId)));
        $this->assertSame(0, (int) wc_get_held_stock_quantity(wc_get_product($productId), $retry->paidOrderId));
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_unit_held_by_the_shoppers_checkout_order_is_refused_with_the_stock_message(string $class, string $id): void
    {
        $this->configureWallet($id);
        $productId = $this->createStockedProduct('Held at checkout', 1);
        // The shopper's own checkout order holds the unit; the cart check leaves it out, the reservation does not.
        $checkoutOrder = wc_create_order();
        $checkoutOrder->add_product(wc_get_product($productId), 1);
        $checkoutOrder->save();
        wc_reserve_stock_for_order($checkoutOrder);
        WC()->session->set('order_awaiting_payment', $checkoutOrder->get_id());
        $gateway = new $class();
        $this->postTransaction($productId);
        $ordersBefore = $this->orderIds();

        try {
            $message = $this->runTransaction($gateway);
        } finally {
            WC()->session->set('order_awaiting_payment', null);
        }

        $this->assertStringContainsString('Not enough units of Held at checkout', $message);
        $this->assertNull($gateway->paidOrderId);
        $this->assertSame($ordersBefore, $this->orderIds());
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_payment_that_could_not_start_releases_its_stock_reservation(string $class, string $id): void
    {
        $this->configureWallet($id);
        $productId = $this->createStockedProduct('Released unit', 1);
        $this->postTransaction($productId);
        $failing = new $class();
        $heldAtPayment = null;
        $failing->beforePayment = static function () use (&$heldAtPayment, $productId): void {
            $heldAtPayment = (int) wc_get_held_stock_quantity(wc_get_product($productId));
        };
        $failing->paymentError = new RuntimeException('Buckaroo is unreachable.');

        $this->assertSame(self::FAILED, $this->runTransaction($failing));

        $this->assertSame(1, $heldAtPayment, 'The order held the unit while its payment started.');
        $this->assertSame(0, (int) wc_get_held_stock_quantity(wc_get_product($productId)));
        $this->startOtherSession();
        $this->assertSame(self::PAID, $this->runTransaction(new $class()));
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_rate_limit_counted_elsewhere_at_the_same_time_refuses_the_request(string $class, string $id): void
    {
        $this->configureWallet($id);
        $gateway = new $class();
        $this->postTransaction($this->createSimpleProduct('Concurrent', '15.00'));
        $ordersBefore = $this->orderIds();
        $other = new mysqli(...$this->databaseArguments());
        $lock = $other->query("SELECT GET_LOCK('" . $other->real_escape_string(ExpressRateLimit::lockName()) . "', 0)")->fetch_row()[0];
        $this->assertSame('1', (string) $lock);

        try {
            $message = $this->runTransaction($gateway);
        } finally {
            $other->close();
        }

        $this->assertSame(self::TOO_MANY, $message);
        $this->assertNull($gateway->paidOrderId);
        $this->assertSame($ordersBefore, $this->orderIds());
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_product_refused_by_add_to_cart_validation_is_rejected(string $class, string $id): void
    {
        $this->configureWallet($id);
        $refuse = static function (): bool {
            wc_add_notice('This product needs a booking date.', 'error');

            return false;
        };
        add_filter('woocommerce_add_to_cart_validation', $refuse);
        $gateway = new $class();
        $this->postTransaction($this->createSimpleProduct('Booking', '15.00'));

        try {
            $message = $this->runTransaction($gateway);
        } finally {
            remove_filter('woocommerce_add_to_cart_validation', $refuse);
        }

        $this->assertSame('This product needs a booking date.', $message);
        $this->assertNull($gateway->paidOrderId);
    }

    /**
     * @dataProvider walletControllers
     */
    public function test_add_to_cart_validation_does_not_block_the_price_quote(string $controller): void
    {
        $refuse = static function (): bool {
            return false;
        };
        add_filter('woocommerce_add_to_cart_validation', $refuse);
        $_GET = ['product_id' => (string) $this->createSimpleProduct('Quoted', '15.00'), 'quantity' => '1'];

        try {
            $items = $this->captureJson([$controller, 'getItemsFromDetailPage']);
        } finally {
            remove_filter('woocommerce_add_to_cart_validation', $refuse);
            $_GET = [];
        }

        $this->assertSame('product', $items[0]['type']);
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_error_from_a_third_party_cart_check_is_rejected(string $class, string $id): void
    {
        $this->configureWallet($id);
        $productId = $this->createSimpleProduct('Minimum quantity', '15.00');
        $this->assertIsString(WC()->cart->add_to_cart($productId, 1));
        $check = static function (): void {
            if (WC()->cart->get_cart_contents_count() < 2) {
                wc_add_notice('Order at least two items.', 'error');
            }
        };
        add_action('woocommerce_check_cart_items', $check);
        $gateway = new $class();
        $this->postTransaction($productId);

        try {
            $message = $this->runTransaction($gateway);
        } finally {
            remove_action('woocommerce_check_cart_items', $check);
        }

        $this->assertSame('Order at least two items.', $message);
        $this->assertNull($gateway->paidOrderId);
        $this->assertSame([], wc_get_notices());
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_error_from_a_checkout_process_validator_is_rejected(string $class, string $id): void
    {
        $this->configureWallet($id);
        $validate = static function (): void {
            wc_add_notice('Please confirm you are over 18.', 'error');
        };
        add_action('woocommerce_checkout_process', $validate);
        $gateway = new $class();
        $this->postTransaction($this->createSimpleProduct('Age check', '15.00'));

        try {
            $message = $this->runTransaction($gateway);
        } finally {
            remove_action('woocommerce_checkout_process', $validate);
        }

        $this->assertSame('Please confirm you are over 18.', $message);
        $this->assertNull($gateway->paidOrderId);
        $this->assertSame([], wc_get_notices());
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_error_from_an_after_checkout_validation_validator_is_rejected(string $class, string $id): void
    {
        $this->configureWallet($id);
        $validate = static function (array $data, WP_Error $errors): void {
            $errors->add('delivery', 'We do not deliver to ' . $data['shipping_postcode'] . '.');
        };
        add_action('woocommerce_after_checkout_validation', $validate, 10, 2);
        $gateway = new $class();
        $this->postTransaction($this->createSimpleProduct('Delivery area', '15.00'));

        try {
            $message = $this->runTransaction($gateway);
        } finally {
            remove_action('woocommerce_after_checkout_validation', $validate, 10);
        }

        $this->assertSame('We do not deliver to 1017GB.', $message);
        $this->assertNull($gateway->paidOrderId);
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_checkout_validators_see_the_wallet_details_in_the_posted_form(string $class, string $id): void
    {
        $this->configureWallet($id);
        $seen = [];
        $validate = static function () use (&$seen): void {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Test validator.
            $seen = wp_unslash(array_intersect_key($_POST, array_flip(['billing_email', 'billing_last_name', 'billing_phone', 'shipping_city', 'payment_method'])));
        };
        add_action('woocommerce_checkout_process', $validate);
        $gateway = new $class();
        $this->postTransaction($this->createSimpleProduct('Posted form', '15.00'), '15.00', 'o\'brien@example.com');
        // Apple Pay returns the phone number with the shipping contact only.
        $_POST['paymentData']['shippingContact']['phoneNumber'] = '+31201234567';

        try {
            $this->assertSame(self::PAID, $this->runTransaction($gateway));
        } finally {
            remove_action('woocommerce_checkout_process', $validate);
        }

        ksort($seen);
        $this->assertSame(
            [
                'billing_email' => 'o\'brien@example.com',
                'billing_last_name' => 'Buyer',
                'billing_phone' => '+31201234567',
                'payment_method' => $id,
                'shipping_city' => 'Amsterdam',
            ],
            $seen
        );
        $this->assertArrayNotHasKey('billing_email', $_POST, 'The request data is restored afterwards.');
    }

    public function test_express_buttons_are_not_rendered_for_a_guest_who_must_log_in(): void
    {
        update_option('woocommerce_enable_guest_checkout', 'no');

        $this->assertSame('', $this->renderedButtons());

        $this->loginCustomer('account@example.com');
        $this->assertStringContainsString('express-button', $this->renderedButtons());
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_blocks_express_button_is_hidden_for_a_guest_who_must_log_in(string $class, string $id): void
    {
        $this->configureWallet($id);
        update_option('woocommerce_enable_guest_checkout', 'no');
        $gateway = new $class();

        $this->assertFalse($this->blocksData($gateway)['showInCheckout']);

        $this->loginCustomer('account@example.com');
        $this->assertTrue($this->blocksData($gateway)['showInCheckout']);
    }

    public function test_blocks_paypal_express_button_is_hidden_for_a_guest_who_must_log_in(): void
    {
        update_option('woocommerce_buckaroo_paypal_settings', ['enabled' => 'yes', 'mode' => 'test', 'express' => ['checkout']]);
        update_option('woocommerce_enable_guest_checkout', 'no');
        $gateway = new PaypalGateway();

        $this->assertFalse($this->blocksData($gateway)['showInCheckout']);

        $this->loginCustomer('account@example.com');
        $this->assertTrue($this->blocksData($gateway)['showInCheckout']);
    }

    private function createStockedProduct(string $name, int $stock): int
    {
        $productId = $this->createSimpleProduct($name, '15.00');
        $product = wc_get_product($productId);
        $product->set_manage_stock(true);
        $product->set_stock_quantity($stock);
        $product->save();

        return $productId;
    }

    /** Continue as another shopper: a session without this shopper's express order or attempts. */
    private function startOtherSession(): void
    {
        WC()->session->set(ExpressCheckoutRules::LAST_ORDER_SESSION_KEY, null);
        WC()->session->set(ExpressRateLimit::SESSION_KEY, null);
    }

    /** Arguments for a second database connection, as a concurrent request has. */
    private function databaseArguments(): array
    {
        $host = DB_HOST;
        $port = null;
        if (strpos($host, ':') !== false) {
            [$host, $port] = explode(':', $host, 2);
        }

        return [$host, DB_USER, DB_PASSWORD, DB_NAME, $port !== null ? (int) $port : null];
    }

    public function walletControllers(): array
    {
        return [
            'Apple Pay' => [ApplepayController::class],
            'Google Pay' => [GooglepayController::class],
        ];
    }

    /** How often the shopper's live cart is recalculated while the callback runs. */
    private function countLiveCartCalculations(callable $callback): int
    {
        $live = WC()->cart;
        $count = 0;
        $counter = static function ($cart) use ($live, &$count): void {
            if ($cart === $live) {
                $count++;
            }
        };
        add_action('woocommerce_before_calculate_totals', $counter);
        try {
            $callback();
        } finally {
            remove_action('woocommerce_before_calculate_totals', $counter);
        }

        return $count;
    }

    private function renderedButtons(): string
    {
        $manager = ExpressPaymentManager::getInstance();
        $manager->clearPayments();
        $manager->registerExpressPayment('test', static function (): void {
            echo '<div class="express-button"></div>';
        }, 'cart');

        ob_start();
        try {
            $manager->renderExpressPaymentsContainer('cart');
        } finally {
            $output = (string) ob_get_clean();
            $manager->clearPayments();
        }

        return $output;
    }

    private function blocksData($gateway): array
    {
        $gateways = WC()->payment_gateways();
        $previous = $gateways->payment_gateways;
        $gateways->payment_gateways = [$gateway->id => $gateway];
        try {
            return (new InitGateways())->initGatewaysOnCheckout()[0];
        } finally {
            $gateways->payment_gateways = $previous;
        }
    }

    private function captureJson(callable $endpoint): array
    {
        $handler = static function () {
            return static function (): void {
            };
        };
        add_filter('wp_doing_ajax', '__return_true');
        add_filter('wp_die_ajax_handler', $handler);
        ob_start();
        try {
            call_user_func($endpoint);
        } finally {
            remove_filter('wp_die_ajax_handler', $handler);
            remove_filter('wp_doing_ajax', '__return_true');
        }

        $response = json_decode((string) ob_get_clean(), true);
        $this->assertIsArray($response);

        return $response;
    }

    private function loginCustomer(string $email): int
    {
        $userId = self::factory()->user->create(['role' => 'customer', 'user_email' => $email]);
        wp_set_current_user($userId);

        return $userId;
    }

    /**
     * A 1.00 fixed cart coupon with the given limits.
     *
     * @param  array<string, mixed>  $props
     */
    private function createCoupon(string $code, array $props): WC_Coupon
    {
        $coupon = new WC_Coupon();
        $coupon->set_code($code);
        $coupon->set_discount_type('fixed_cart');
        $coupon->set_amount(1);
        $coupon->set_props($props);
        $coupon->save();

        return $coupon;
    }

    /**
     * Put a 15.00 product in the live cart with the coupon applied, as on the
     * cart page; returns the product id.
     */
    private function cartWithCoupon(WC_Coupon $coupon): int
    {
        $productId = $this->createSimpleProduct('Coupon product', '15.00');
        $this->assertIsString(WC()->cart->add_to_cart($productId, 1));
        $this->assertTrue(WC()->cart->apply_coupon($coupon->get_code()));
        wc_clear_notices();

        return $productId;
    }
}
