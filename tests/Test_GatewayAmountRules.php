<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\Ideal\IdealGateway;
use Buckaroo\Woocommerce\Gateways\ZakelijkOpRekening\ZakelijkOpRekeningGateway;
use Buckaroo\Woocommerce\Hooks\DisableGateways;
use PHPUnit\Framework\TestCase;

/**
 * Amount and currency rules are evaluated against the amount being paid:
 * the order on order-pay and at process_payment, not the session cart.
 */
class Test_GatewayAmountRules extends TestCase
{
    protected function tearDown(): void
    {
        set_query_var('order-pay', '');
        if (function_exists('wc_clear_notices')) {
            wc_clear_notices();
        }
    }

    public function test_is_allowed_for_applies_min_max_and_currency(): void
    {
        $gateway = new IdealGateway();
        $gateway->minvalue = '10';
        $gateway->maxvalue = '100';

        $this->assertTrue($gateway->isAllowedFor(50.0, 'EUR'));
        $this->assertFalse($gateway->isAllowedFor(5.0, 'EUR'));
        $this->assertFalse($gateway->isAllowedFor(150.0, 'EUR'));
        $this->assertFalse($gateway->isAllowedFor(50.0, 'USD'));
        $this->assertTrue($gateway->isAllowedFor(0.0, 'EUR'), 'A zero amount skips the amount checks.');
    }

    public function test_is_allowed_for_applies_method_specific_range(): void
    {
        $gateway = new ZakelijkOpRekeningGateway();
        $gateway->minvalue = 0;
        $gateway->maxvalue = 0;

        $this->assertFalse($gateway->isAllowedFor(ZakelijkOpRekeningGateway::MIN_AMOUNT - 1, 'EUR'));
        $this->assertFalse($gateway->isAllowedFor(ZakelijkOpRekeningGateway::MAX_AMOUNT + 1, 'EUR'));
    }

    public function test_order_pay_uses_order_total_instead_of_cart(): void
    {
        $gateway = $this->visibleIdeal('100');
        $order = $this->order(500.0, 'EUR');
        set_query_var('order-pay', $order->get_id());

        $available = (new DisableGateways())->handle([$gateway->id => $gateway]);

        $this->assertArrayNotHasKey($gateway->id, $available, 'Order total above maxvalue must hide the gateway on order-pay.');
        $order->delete(true);
    }

    public function test_order_pay_uses_order_currency(): void
    {
        $gateway = $this->visibleIdeal('0');
        $order = $this->order(50.0, 'USD');
        set_query_var('order-pay', $order->get_id());

        $available = (new DisableGateways())->handle([$gateway->id => $gateway]);

        $this->assertArrayNotHasKey($gateway->id, $available, 'Unsupported order currency must hide the gateway on order-pay.');
        $order->delete(true);
    }

    public function test_order_pay_keeps_gateway_within_rules(): void
    {
        $gateway = $this->visibleIdeal('100');
        $order = $this->order(50.0, 'EUR');
        set_query_var('order-pay', $order->get_id());

        $available = (new DisableGateways())->handle([$gateway->id => $gateway]);

        $this->assertArrayHasKey($gateway->id, $available);
        $order->delete(true);
    }

    public function test_process_payment_rejects_order_outside_rules(): void
    {
        $gateway = new IdealGateway();
        $gateway->maxvalue = '100';
        $order = $this->order(500.0, 'EUR');

        $result = $gateway->process_payment($order->get_id());

        $this->assertSame(['result' => 'failure'], $result);
        $order->delete(true);
    }

    public function test_process_payment_rejects_zakelijk_order_outside_scheme_limits(): void
    {
        $gateway = new ZakelijkOpRekeningGateway();
        $order = $this->order(ZakelijkOpRekeningGateway::MAX_AMOUNT + 1, 'EUR');

        $result = $gateway->process_payment($order->get_id());

        $this->assertSame(['result' => 'failure'], $result);
        $notices = wp_list_pluck(wc_get_notices('error'), 'notice');
        $this->assertContains(
            'This payment method is not available for this order. Please choose another payment method.',
            $notices,
            'Rejected before the authorize call, not by its error handling.'
        );
        $order->delete(true);
    }

    private function visibleIdeal(string $maxvalue): IdealGateway
    {
        $gateway = new class () extends IdealGateway {
            public function isVisibleInCheckout(): bool
            {
                return true;
            }
        };
        $gateway->minvalue = 0;
        $gateway->maxvalue = $maxvalue;

        return $gateway;
    }

    private function order(float $total, string $currency): WC_Order
    {
        $order = wc_create_order();
        $order->set_billing_country('NL');
        $order->set_currency($currency);
        $order->set_total($total);
        $order->save();

        return $order;
    }
}
