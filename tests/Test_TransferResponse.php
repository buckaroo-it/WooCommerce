<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\Transfer\TransferResponse;
use Buckaroo\Woocommerce\ResponseParser\ResponseParser;
use Buckaroo\Woocommerce\ResponseParser\ResponseRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Bank transfer details are shown to the customer as payment instructions,
 * so they may only come from a validated reply for a transfer order.
 */
class Test_TransferResponse extends TestCase
{
    use HposStorage;

    private const BANK_META_KEYS = [
        'buckaroo_IBAN',
        'buckaroo_BIC',
        'buckaroo_accountHolderName',
        'buckaroo_paymentReference',
    ];

    protected function tearDown(): void
    {
        $this->deleteCreatedOrders();
        $this->disableHpos();
        parent::tearDown();
    }

    public function test_parsing_a_transfer_reply_does_not_write_to_the_order()
    {
        $order = $this->createTransferOrder();

        ResponseRegistry::getResponse($this->formTransferReply($order));

        $this->assertNoBankDetails($order);
    }

    public function test_parsing_a_transfer_reply_does_not_write_to_the_order_on_hpos()
    {
        $this->enableHpos();
        $order = $this->createTransferOrder();

        ResponseRegistry::getResponse($this->formTransferReply($order));

        $this->assertNoBankDetails($order);
    }

    public function test_store_bank_details_saves_the_fields_of_a_transfer_pay_response()
    {
        $order = $this->createTransferOrder();
        $parser = ResponseParser::make([
            'ServiceCode' => 'transfer',
            'Services' => [
                [
                    'Name' => 'transfer',
                    'Parameters' => [
                        ['Name' => 'IBAN', 'Value' => 'NL00TEST0000000001'],
                        ['Name' => 'BIC', 'Value' => 'TESTNL2A'],
                        ['Name' => 'AccountHolderName', 'Value' => 'Test Holder'],
                        ['Name' => 'PaymentReference', 'Value' => 'REF-1'],
                    ],
                ],
            ],
        ]);

        (new TransferResponse($parser))->storeBankDetails($order);

        $stored = wc_get_order($order->get_id());
        $this->assertSame('NL00TEST0000000001', $stored->get_meta('buckaroo_IBAN'));
        $this->assertSame('TESTNL2A', $stored->get_meta('buckaroo_BIC'));
        $this->assertSame('Test Holder', $stored->get_meta('buckaroo_accountHolderName'));
        $this->assertSame('REF-1', $stored->get_meta('buckaroo_paymentReference'));
    }

    public function test_store_bank_details_saves_the_fields_of_a_transfer_push()
    {
        $order = $this->createTransferOrder();
        $parser = ResponseParser::make($this->formTransferReply($order));

        (new TransferResponse($parser))->storeBankDetails($order);

        $stored = wc_get_order($order->get_id());
        $this->assertSame('NL00TEST0000000002', $stored->get_meta('buckaroo_IBAN'));
        $this->assertSame('REF-2', $stored->get_meta('buckaroo_paymentReference'));
    }

    public function test_store_bank_details_ignores_orders_paid_with_another_method()
    {
        $order = $this->createOrder();
        $order->set_payment_method('buckaroo_ideal');
        $order->save();
        $parser = ResponseParser::make($this->formTransferReply($order));

        (new TransferResponse($parser))->storeBankDetails($order);

        $this->assertNoBankDetails($order);
    }

    public function test_store_bank_details_ignores_replies_for_another_method()
    {
        $order = $this->createTransferOrder();
        $reply = $this->formTransferReply($order);
        $reply['brq_transaction_method'] = 'ideal';
        $parser = ResponseParser::make($reply);

        (new TransferResponse($parser))->storeBankDetails($order);

        $this->assertNoBankDetails($order);
    }

    private function createTransferOrder(): WC_Order
    {
        $order = $this->createOrder();
        $order->set_payment_method('buckaroo_transfer');
        $order->save();

        return wc_get_order($order->get_id());
    }

    private function formTransferReply(WC_Order $order): array
    {
        return [
            'brq_statuscode' => '792',
            'brq_transaction_method' => 'transfer',
            'brq_ordernumber' => (string) $order->get_id(),
            'brq_SERVICE_transfer_IBAN' => 'NL00TEST0000000002',
            'brq_SERVICE_transfer_BIC' => 'TESTNL2A',
            'brq_SERVICE_transfer_AccountHolderName' => 'Test Holder',
            'brq_SERVICE_transfer_PaymentReference' => 'REF-2',
        ];
    }

    private function assertNoBankDetails(WC_Order $order): void
    {
        $stored = wc_get_order($order->get_id());

        foreach (self::BANK_META_KEYS as $metaKey) {
            $this->assertSame('', $stored->get_meta($metaKey), "$metaKey must not be written");
        }
    }
}
