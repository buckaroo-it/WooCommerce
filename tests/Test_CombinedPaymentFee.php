<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Hooks\OrderActions;

class Test_CombinedPaymentFee extends WP_UnitTestCase
{
    public function test_cart_adds_combined_fee_once_on_discounted_products(): void
    {
        $cart = new WC_Cart();
        $cart->set_cart_contents_total(80);
        $cart->set_shipping_total(10);
        $cart->add_fee('Unrelated fee', 5);
        $actions = new OrderActions();
        $actions->add_fee_to_cart($cart, '0.25 + 1.5%', 'reduced-rate');
        $actions->add_fee_to_cart($cart, '0.25 + 1.5%', 'reduced-rate');

        $fees = $cart->get_fees();
        $this->assertCount(2, $fees);
        $this->assertSame(1.45, $fees['payment-fee']->amount);
        $this->assertTrue($fees['payment-fee']->taxable);
        $this->assertSame('reduced-rate', $fees['payment-fee']->tax_class);
        $this->assertSame(5.0, (float) $fees['unrelated-fee']->amount);
    }

    /** @dataProvider orderFees */
    public function test_saved_order_fallback_matches_discounted_cart_and_is_idempotent(string $raw, string $amount, string $total, bool $taxed = false): void
    {
        update_option('woocommerce_buckaroo_mastersettings_settings', ['culture' => 'en-US']);
        $rateId = null;
        if ($taxed) {
            update_option('woocommerce_calc_taxes', 'yes');
            $rateId = WC_Tax::_insert_tax_rate([
                'tax_rate_country' => 'NL', 'tax_rate' => '21.0000',
                'tax_rate_name' => 'VAT', 'tax_rate_priority' => 1,
                'tax_rate_compound' => 0, 'tax_rate_shipping' => 1, 'tax_rate_class' => '',
            ]);
        }
        $order = wc_create_order();
        $order->set_billing_country('NL');
        $order->set_shipping_country('NL');
        $item = new WC_Order_Item_Product();
        $item->set_name('Discounted product');
        $item->set_subtotal(100);
        $item->set_total(80);
        $order->add_item($item);
        $shipping = new WC_Order_Item_Shipping();
        $shipping->set_total(10);
        $order->add_item($shipping);
        $other = new WC_Order_Item_Fee();
        $other->set_name('Unrelated');
        $other->set_total(5);
        $order->add_item($other);
        $order->calculate_totals(false);
        $gateway = new \Buckaroo\Woocommerce\Gateways\Ideal\IdealGateway();
        $gateway->settings['extrachargeamount'] = $raw;
        $details = new \Buckaroo\Woocommerce\Order\OrderDetails($order);
        $processor = new \Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor(
            $gateway, $details, new \Buckaroo\Woocommerce\Order\OrderArticles($details, $gateway)
        );
        try {
            $this->assertSame($total, $processor->getBody()['amountDebit']);
            $this->assertSame($total, $processor->getBody()['amountDebit']);
            $saved = wc_get_order($order->get_id());
            $this->assertCount(2, $saved->get_fees());
            $fees = array_values(array_filter($saved->get_fees(), static function ($fee) {
                return $fee->get_name() === 'Payment fee';
            }));
            $this->assertCount(1, $fees);
            $this->assertSame($amount, $fees[0]->get_total());
            $this->assertSame($taxed ? 0.3 : 0.0, (float) $fees[0]->get_total_tax());
        } finally {
            $order->delete(true);
            if ($rateId !== null) { WC_Tax::_delete_tax_rate($rateId); }
        }
    }

    public function test_title_and_blocks_fee_flag_support_zero_fixed_combined_fee(): void
    {
        $gateway = new \Buckaroo\Woocommerce\Gateways\Ideal\IdealGateway();
        $gateway->enabled = 'yes';
        $gateway->settings['title'] = 'Test method';
        $gateway->settings['extrachargeamount'] = '0 + 1.5%';
        $gateway->setTitle();
        $this->assertStringContainsString('1.50%', $gateway->title);
        $before = WC()->payment_gateways()->payment_gateways;
        WC()->payment_gateways()->payment_gateways = [$gateway];
        try {
            $methods = (new \Buckaroo\Woocommerce\Hooks\InitGateways())->initGatewaysOnCheckout();
            $this->assertCount(1, $methods);
            $this->assertTrue($methods[0]['hasFee']);
            foreach (['0', '0 + 0%', 'bad', '-1', []] as $raw) {
                $gateway->settings['extrachargeamount'] = $raw;
                $gateway->setTitle();
                $this->assertSame('Test method', $gateway->title);
                $methods = (new \Buckaroo\Woocommerce\Hooks\InitGateways())->initGatewaysOnCheckout();
                $this->assertFalse($methods[0]['hasFee']);
            }
        } finally {
            WC()->payment_gateways()->payment_gateways = $before;
        }
    }

    public function orderFees(): array
    {
        return [
            'combined discounted base' => ['0.25 + 1.5%', '1.45', '96.45'],
            'combined fee tax' => ['0.25 + 1.5%', '1.45', '96.75', true],
            'zero fixed' => ['0 + 1.5%', '1.2', '96.20'],
            'legacy fixed' => ['2.50', '2.5', '97.50'],
            'legacy percentage subtotal' => ['1.5%', '1.5', '96.50'],
        ];
    }

    public function test_settings_save_reload_and_titles_keep_both_components(): void
    {
        $gateway = new \Buckaroo\Woocommerce\Gateways\Ideal\IdealGateway();
        $before = $_POST;
        $_POST[$gateway->get_field_key('extrachargeamount')] = '0.25 + 1.5%';
        $_POST[$gateway->get_field_key('title')] = 'Test method';
        try {
            $gateway->process_admin_options();
            $loaded = new \Buckaroo\Woocommerce\Gateways\Ideal\IdealGateway();
            $this->assertSame('0.25 + 1.5%', $loaded->get_option('extrachargeamount'));
            $this->assertStringContainsString('0.25', $loaded->title);
            $this->assertStringContainsString(' + 1.50%', $loaded->title);
            $this->assertStringContainsString('0.25 + 1.5%', $loaded->form_fields['extrachargeamount']['description']);
            $payByBank = new \Buckaroo\Woocommerce\Gateways\PayByBank\PayByBankGateway();
            $this->assertArrayNotHasKey('extrachargeamount', $payByBank->form_fields);
        } finally {
            $_POST = $before;
        }
    }

    public function test_cart_recalculation_removes_fee_after_switching_to_no_fee_method(): void
    {
        if (! WC()->session) { WC()->initialize_session(); }
        if (! WC()->customer) { WC()->customer = new WC_Customer(0, true); }
        $original = WC()->cart;
        WC()->cart = new WC_Cart();
        $product = new WC_Product_Simple();
        $product->set_regular_price('100');
        $product->save();
        $coupon = new WC_Coupon();
        $coupon->set_code('combined-fee-test');
        $coupon->set_discount_type('fixed_cart');
        $coupon->set_amount(20);
        $coupon->save();
        $gateway = new \Buckaroo\Woocommerce\Gateways\Ideal\IdealGateway();
        $gateway->enabled = 'yes';
        $gateway->settings['extrachargeamount'] = '0.25 + 1.5%';
        $gateways = WC()->payment_gateways()->payment_gateways;
        WC()->payment_gateways()->payment_gateways = [$gateway];
        WC()->customer->set_billing_country('NL');
        WC()->customer->set_shipping_country('NL');
        WC()->session->set('chosen_payment_method', $gateway->id);
        try {
            WC()->cart->add_to_cart($product->get_id());
            WC()->cart->apply_coupon('combined-fee-test');
            WC()->cart->calculate_totals();
            $fees = WC()->cart->get_fees();
            $this->assertSame(1.45, (float) $fees['payment-fee']->amount);
            WC()->session->set('chosen_payment_method', 'cod');
            WC()->cart->calculate_totals();
            $this->assertCount(0, WC()->cart->get_fees());
            $this->assertSame(80.0, (float) WC()->cart->get_total('edit'));
        } finally {
            WC()->payment_gateways()->payment_gateways = $gateways;
            WC()->cart = $original;
            $product->delete(true);
            $coupon->delete(true);
        }
    }

    /** @dataProvider googlePayAmounts */
    public function test_google_pay_requires_authorized_amount_to_match_order($authorized, bool $valid): void
    {
        update_option('woocommerce_buckaroo_mastersettings_settings', ['culture' => 'en-US']);
        $before = $_POST;
        $_POST = $authorized === null ? [] : ['amount' => $authorized];
        $order = wc_create_order();
        $item = new WC_Order_Item_Product();
        $item->set_name('Album');
        $item->set_subtotal(15);
        $item->set_total(15);
        $order->add_item($item);
        $order->calculate_totals(false);
        $gateway = new \Buckaroo\Woocommerce\Gateways\Googlepay\GooglepayGateway();
        $gateway->settings['extrachargeamount'] = '0.25 + 1.5%';
        $details = new \Buckaroo\Woocommerce\Order\OrderDetails($order);
        $processor = new \Buckaroo\Woocommerce\Gateways\Googlepay\GooglepayProcessor(
            $gateway, $details, new \Buckaroo\Woocommerce\Order\OrderArticles($details, $gateway)
        );
        try {
            if (! $valid) {
                $this->expectException(\InvalidArgumentException::class);
                $this->expectExceptionMessage('Google Pay amount');
            }
            $body = $processor->getBody();
            $this->assertSame('15.48', $body['amountDebit']);
        } finally {
            $_POST = $before;
            $order->delete(true);
        }
    }

    public function googlePayAmounts(): array
    {
        return [
            'missing fee' => ['15.00', false],
            'matches' => ['15.48', true],
            'overpayment' => ['20', false],
            'malformed' => ['15.48bad', false],
            'invalid type' => [[], false],
            'standard checkout' => [null, true],
        ];
    }
}
