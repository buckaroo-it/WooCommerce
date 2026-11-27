<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\SepaDirectDebit\SepaDirectDebitGateway;
use Buckaroo\Woocommerce\PaymentProcessors\ReturnProcessor;

/**
 * The mandate of a SEPA Direct Debit payment is shown to the customer in one
 * order note. Buckaroo usually answers the payment with 791, pending
 * processing (BTI-1880).
 */
class Test_SepaDirectDebitMandateNote extends WP_UnitTestCase
{
    /** @var WC_Order|null */
    private $order;

    protected function setUp(): void
    {
        parent::setUp();

        update_option('woocommerce_buckaroo_mastersettings_settings', [
            'culture' => 'en-US',
            'merchantkey' => 'website-key',
            'secretkey' => 'secret-key',
        ]);
        if (function_exists('wc_load_cart') && WC()->cart === null) {
            wc_load_cart();
        }

        $this->order = wc_create_order();
        $this->order->set_payment_method('buckaroo_sepadirectdebit');
        $this->order->set_currency('EUR');
        $this->order->set_total('10.00');
        $this->order->save();
    }

    protected function tearDown(): void
    {
        if ($this->order instanceof WC_Order) {
            $this->order->delete(true);
        }
        delete_option('woocommerce_buckaroo_mastersettings_settings');
        wc_clear_notices();

        parent::tearDown();
    }

    public static function statuses(): array
    {
        return ['pending processing' => [791], 'success' => [190]];
    }

    /**
     * @dataProvider statuses
     */
    public function test_the_mandate_is_added_in_one_customer_note(int $status): void
    {
        $this->handle($status, [
            ['Name' => 'MandateReference', 'Value' => '1DC81C70566CB634566BFA1F86FF3AEDBCD'],
            ['Name' => 'MandateDate', 'Value' => '2025-07-01'],
            ['Name' => 'CollectDate', 'Value' => '2025-07-04'],
        ]);

        $notes = $this->mandateNotes();
        $this->assertCount(1, $notes);
        $this->assertTrue((bool) $notes[0]->customer_note);
        $this->assertStringContainsString('1DC81C70566CB634566BFA1F86FF3AEDBCD', $notes[0]->content);
        $this->assertStringContainsString('2025-07-01', $notes[0]->content);
    }

    public function test_a_response_without_mandate_adds_no_note(): void
    {
        $this->handle(791, [['Name' => 'CollectDate', 'Value' => '2025-07-04']]);

        $this->assertSame([], $this->mandateNotes());
    }

    private function handle(int $status, array $parameters): void
    {
        (new ReturnProcessor([
            'Key' => 'A1B2C3D4E5F60718293A4B5C6D7E8F90',
            'Status' => ['Code' => ['Code' => $status, 'Description' => '']],
            'ServiceCode' => 'SepaDirectDebit',
            'Services' => [['Name' => 'SepaDirectDebit', 'Action' => null, 'Parameters' => $parameters]],
            'Invoice' => (string) $this->order->get_id(),
            'Currency' => 'EUR',
            'AmountDebit' => 10.00,
            'IsTest' => true,
        ], false))->handle(new SepaDirectDebitGateway());
    }

    private function mandateNotes(): array
    {
        $notes = wc_get_order_notes(['order_id' => $this->order->get_id()]);

        return array_values(array_filter($notes, static function ($note) {
            return strpos($note->content, 'Mandate') !== false;
        }));
    }
}
