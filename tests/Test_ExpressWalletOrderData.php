<?php

declare(strict_types=1);

/**
 * An express wallet order stores the wallet contact as the shopper entered it,
 * and notes which wallet created it (BTI-1880).
 */
class Test_ExpressWalletOrderData extends ExpressWalletTestCase
{
    /**
     * WordPress slash-escapes the posted wallet payload; the order must not
     * keep those slashes.
     *
     * @dataProvider walletGateways
     */
    public function test_the_wallet_contact_is_stored_without_slashes(string $class, string $id): void
    {
        $this->configureWallet($id);
        $gateway = new $class();
        $this->postTransaction($this->createSimpleProduct('Apostrophe', '15.00'));
        foreach (['billingContact', 'shippingContact'] as $contact) {
            $_POST['paymentData'][$contact]['familyName'] = "O'Brien";
            $_POST['paymentData'][$contact]['locality'] = "'s-Hertogenbosch";
        }
        $_POST = wp_slash($_POST);

        $this->assertSame(self::PAID, $this->runTransaction($gateway));

        $order = wc_get_order($gateway->paidOrderId);
        $this->assertSame("O'Brien", $order->get_billing_last_name());
        $this->assertSame("'s-Hertogenbosch", $order->get_billing_city());
        $this->assertSame("O'Brien", $order->get_shipping_last_name());
        $this->assertSame("'s-Hertogenbosch", $order->get_shipping_city());
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_the_order_notes_which_wallet_created_it(string $class, string $id): void
    {
        $this->configureWallet($id);
        $gateway = new $class();
        $this->postTransaction($this->createSimpleProduct('Noted', '15.00'));

        $this->assertSame(self::PAID, $this->runTransaction($gateway));

        $order = wc_get_order($gateway->paidOrderId);
        $wallet = $id === 'buckaroo_applepay' ? 'Apple Pay' : 'Google Pay';
        $this->assertSame('pending', $order->get_status());
        $this->assertContains(
            'Order created using ' . $wallet,
            wp_list_pluck(wc_get_order_notes(['order_id' => $order->get_id()]), 'content')
        );
    }
}
