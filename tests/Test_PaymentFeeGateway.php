<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;
use Buckaroo\Woocommerce\Gateways\Ideal\IdealGateway;
use Buckaroo\Woocommerce\Hooks\OrderActions;
use Buckaroo\Woocommerce\Order\OrderArticles;
use Buckaroo\Woocommerce\Order\OrderDetails;

class Test_PaymentFeeGateway extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        update_option('woocommerce_calc_taxes', 'no');
        update_option('woocommerce_buckaroo_mastersettings_settings', ['culture' => 'en-US']);
    }

    /** @dataProvider mismatchedFees */
    public function test_fee_from_another_method_is_replaced_by_the_paying_gateway_fee(?string $feeGateway, string $configured, ?string $expectedFee, string $expectedTotal): void
    {
        $order = $this->createOrder(5.00, $feeGateway);
        $gateway = new IdealGateway();
        $gateway->settings['extrachargeamount'] = $configured;
        try {
            $body = $this->processor($order, $gateway)->getBody();

            $this->assertSame($expectedTotal, $body['amountDebit']);
            $saved = wc_get_order($order->get_id());
            $paymentFees = $this->paymentFees($saved);
            if ($expectedFee === null) {
                $this->assertSame([], $paymentFees);
            } else {
                $this->assertCount(1, $paymentFees);
                $this->assertSame($expectedFee, $paymentFees[0]->get_total());
                $this->assertSame($gateway->id, $paymentFees[0]->get_meta(AbstractPaymentProcessor::FEE_GATEWAY_META));
            }
            $this->assertSame($expectedTotal, $this->processor($saved, $gateway)->getBody()['amountDebit']);
        } finally {
            $order->delete(true);
        }
    }

    public function mismatchedFees(): array
    {
        return [
            'other method, cheaper paying fee' => ['buckaroo_creditcard', '2.50', '2.5', '102.50'],
            'other method, dearer paying fee' => ['buckaroo_creditcard', '7.50', '7.5', '107.50'],
            'other method, paying gateway has no fee' => ['buckaroo_creditcard', '0', null, '100.00'],
            'untagged legacy fee' => [null, '2.50', '2.5', '102.50'],
        ];
    }

    public function test_fee_calculated_for_the_paying_gateway_is_kept(): void
    {
        $gateway = new IdealGateway();
        $gateway->settings['extrachargeamount'] = '2.50';
        $order = $this->createOrder(5.00, $gateway->id);
        $feeId = $this->paymentFees($order)[0]->get_id();
        try {
            $this->assertSame('105.00', $this->processor($order, $gateway)->getBody()['amountDebit']);
            $paymentFees = $this->paymentFees(wc_get_order($order->get_id()));
            $this->assertCount(1, $paymentFees);
            $this->assertSame($feeId, $paymentFees[0]->get_id());
            $this->assertSame('5', $paymentFees[0]->get_total());
        } finally {
            $order->delete(true);
        }
    }

    public function test_order_fee_item_is_tagged_with_the_method_the_cart_fee_was_calculated_for(): void
    {
        if (! WC()->session) {
            WC()->initialize_session();
        }
        WC()->session->set('chosen_payment_method', 'buckaroo_creditcard');
        $actions = new OrderActions();

        $paymentFee = new WC_Order_Item_Fee();
        $actions->tag_payment_fee_item($paymentFee, 'payment-fee', (object) ['id' => 'payment-fee']);
        $otherFee = new WC_Order_Item_Fee();
        $actions->tag_payment_fee_item($otherFee, 'other-fee', (object) ['id' => 'other-fee']);

        $this->assertSame('buckaroo_creditcard', $paymentFee->get_meta(AbstractPaymentProcessor::FEE_GATEWAY_META));
        $this->assertFalse($otherFee->meta_exists(AbstractPaymentProcessor::FEE_GATEWAY_META));
    }

    public function test_fee_ajax_stores_only_a_registered_gateway_in_the_session(): void
    {
        if (! WC()->session) {
            WC()->initialize_session();
        }
        $before = $_POST;
        $gateways = WC()->payment_gateways()->payment_gateways;
        WC()->payment_gateways()->payment_gateways = ['buckaroo_ideal' => new IdealGateway()];
        WC()->session->set('chosen_payment_method', 'buckaroo_ideal');
        try {
            $_POST['method'] = 'buckaroo_unknown';
            (new OrderActions())->calculate_order_fees();
            $this->assertSame('buckaroo_ideal', WC()->session->get('chosen_payment_method'));

            $_POST['method'] = ['buckaroo_ideal'];
            (new OrderActions())->calculate_order_fees();
            $this->assertSame('buckaroo_ideal', WC()->session->get('chosen_payment_method'));
        } finally {
            $_POST = $before;
            WC()->payment_gateways()->payment_gateways = $gateways;
        }
    }

    private function createOrder(float $fee, ?string $feeGateway): WC_Order
    {
        $order = wc_create_order();
        $order->set_billing_country('NL');
        $item = new WC_Order_Item_Product();
        $item->set_name('Product');
        $item->set_subtotal(100);
        $item->set_total(100);
        $order->add_item($item);
        $paymentFee = new WC_Order_Item_Fee();
        $paymentFee->set_name('Payment fee');
        $paymentFee->set_total((string) $fee);
        if ($feeGateway !== null) {
            $paymentFee->add_meta_data(AbstractPaymentProcessor::FEE_GATEWAY_META, $feeGateway, true);
        }
        $order->add_item($paymentFee);
        $order->calculate_totals(false);
        $order->save();

        return $order;
    }

    private function processor(WC_Order $order, IdealGateway $gateway): AbstractPaymentProcessor
    {
        $details = new OrderDetails($order);

        return new AbstractPaymentProcessor($gateway, $details, new OrderArticles($details, $gateway));
    }

    /** @return WC_Order_Item_Fee[] */
    private function paymentFees(WC_Order $order): array
    {
        return array_values(array_filter($order->get_fees(), static function ($fee) {
            return $fee->get_name() === 'Payment fee';
        }));
    }
}
