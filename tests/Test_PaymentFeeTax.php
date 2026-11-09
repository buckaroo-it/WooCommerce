<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;
use Buckaroo\Woocommerce\Gateways\Ideal\IdealGateway;
use Buckaroo\Woocommerce\Hooks\OrderActions;
use Buckaroo\Woocommerce\Order\OrderArticles;
use Buckaroo\Woocommerce\Order\OrderDetails;

class Test_PaymentFeeTax extends WP_UnitTestCase
{
    /** @dataProvider feeTaxModes */
    public function test_cart_and_order_follow_price_tax_mode(string $raw, bool $inclusive, float $expected, string $feeRate = '21', bool $discounted = false, bool $taxEnabled = true, bool $vatExempt = false): void
    {
        update_option('woocommerce_calc_taxes', $taxEnabled ? 'yes' : 'no');
        update_option('woocommerce_prices_include_tax', $inclusive ? 'yes' : 'no');
        update_option('woocommerce_tax_based_on', 'shipping');
        update_option('woocommerce_buckaroo_mastersettings_settings', ['culture' => 'en-US']);
        WC_Tax::create_tax_class('Fee tax', 'fee-tax');
        $rate = WC_Tax::_insert_tax_rate([
            'tax_rate_country' => 'NL', 'tax_rate' => $feeRate,
            'tax_rate_name' => 'Fee VAT', 'tax_rate_priority' => 1,
            'tax_rate_compound' => 0, 'tax_rate_shipping' => 0, 'tax_rate_class' => 'fee-tax',
        ]);
        $customerBefore = WC()->customer;
        WC()->customer = new WC_Customer();
        WC()->customer->set_billing_location('NL', '', '1017GB', 'Amsterdam');
        WC()->customer->set_shipping_location('NL', '', '1017GB', 'Amsterdam');
        WC()->customer->set_calculated_shipping(true);
        WC()->customer->set_is_vat_exempt($vatExempt);
        $order = wc_create_order();
        $order->set_billing_country('NL');
        $order->set_shipping_country('NL');
        $order->update_meta_data('is_vat_exempt', $vatExempt ? 'yes' : 'no');
        $gross = $discounted ? 8.0 : 10.0;
        $net = $inclusive && $taxEnabled ? $gross / 1.21 : $gross;
        $productTax = $taxEnabled && ! $vatExempt ? round($net * 0.21, 2) : 0.0;
        $cart = new WC_Cart();
        $cart->set_cart_contents_total($net);
        $cart->set_cart_contents_tax($productTax);
        $cart->set_shipping_total(3);
        $cart->add_fee('Other fee', 2);
        $item = new WC_Order_Item_Product();
        $item->set_name('Product');
        $item->set_subtotal($inclusive && $taxEnabled ? 10 / 1.21 : 10);
        $item->set_total($net);
        $item->set_taxes(['total' => [$rate => $productTax], 'subtotal' => [$rate => $productTax]]);
        $order->add_item($item);
        $shipping = new WC_Order_Item_Shipping();
        $shipping->set_total(3);
        $order->add_item($shipping);
        $order->calculate_totals(false);
        $gateway = new IdealGateway();
        $gateway->settings['extrachargeamount'] = $raw;
        $gateway->settings['feetax'] = 'fee-tax';
        $details = new OrderDetails($order);
        $processor = new AbstractPaymentProcessor($gateway, $details, new OrderArticles($details, $gateway));
        try {
            $actions = new OrderActions();
            $actions->add_fee_to_cart($cart, $raw, 'fee-tax');
            $actions->add_fee_to_cart($cart, $raw, 'fee-tax');
            $fees = $cart->get_fees();
            $this->assertCount(2, $fees);
            $fee = $fees['payment-fee'];
            $tax = $taxEnabled && ! $vatExempt ? array_sum(WC_Tax::calc_tax($fee->amount, WC_Tax::get_rates('fee-tax'), false)) : 0;
            $this->assertSame($expected, round($fee->amount + $tax, 2));
            $body = $processor->getBody();
            $this->assertSame($body['amountDebit'], $processor->getBody()['amountDebit']);
            $saved = wc_get_order($order->get_id());
            $this->assertCount(1, $saved->get_fees());
            $savedFee = current($saved->get_fees());
            $this->assertEqualsWithDelta($fee->amount, (float) $savedFee->get_total(), 0.0001);
            $this->assertSame($expected, round((float) $savedFee->get_total() + (float) $savedFee->get_total_tax(), 2));
            $this->assertSame(round($net + $productTax + 3 + $expected, 2), (float) $body['amountDebit']);
        } finally {
            $order->delete(true);
            WC()->customer = $customerBefore;
            WC_Tax::_delete_tax_rate($rate);
        }
    }

    public function feeTaxModes(): array
    {
        return [
            'inclusive percentage first' => ['50% + 1', true, 6.0],
            'inclusive fixed first' => ['1 + 50%', true, 6.5],
            'inclusive fixed first discounted' => ['1 + 50%', true, 5.5, '21', true],
            'exclusive fixed first' => ['1 + 50%', false, 7.87],
            'inclusive fixed' => ['1', true, 1.0],
            'inclusive percentage' => ['50%', true, 5.0],
            'inclusive zero percentage' => ['1 + 0%', true, 1.0],
            'inclusive zero fixed' => ['0 + 50%', true, 5.0],
            'inclusive discounted' => ['50% + 1', true, 5.0, '21', true],
            'inclusive percentage discounted' => ['50%', true, 4.0, '21', true],
            'different fee tax' => ['50% + 1', true, 6.0, '9'],
            'zero fee tax' => ['50% + 1', true, 6.0, '0'],
            'tax disabled' => ['50% + 1', true, 6.0, '21', false, false],
            'exempt customer' => ['50% + 1', true, 5.13, '21', false, true, true],
            'exempt fixed' => ['1', true, 1.0, '21', false, true, true],
            'exclusive combined' => ['50% + 1', false, 7.26],
            'exclusive fixed' => ['1', false, 1.21],
            'exclusive percentage' => ['50%', false, 6.05],
        ];
    }

    public function test_inclusive_fixed_fee_title_does_not_add_tax_again(): void
    {
        update_option('woocommerce_calc_taxes', 'yes');
        update_option('woocommerce_prices_include_tax', 'yes');
        $gateway = new IdealGateway();
        $gateway->settings['extrachargeamount'] = '50% + 1';
        $gateway->settings['paymentfeevat'] = 'off';
        add_filter('woocommerce_is_checkout', '__return_true');
        try {
            $this->assertSame(0, $gateway->getPaymentFeeVat(1));
            $gateway->setTitle();
            $this->assertStringContainsString('1.00', $gateway->title);
        } finally {
            remove_filter('woocommerce_is_checkout', '__return_true');
        }
    }
}
