<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Services\Helper;
use BuckarooDeps\Buckaroo\Resources\Constants\ResponseStatus;
use PHPUnit\Framework\TestCase;

/**
 * Test Helper service class
 */
class Test_Helper extends TestCase
{
    use HposStorage;

    protected function tearDown(): void
    {
        WC()->session->order_awaiting_payment = null;
        $this->deleteCreatedOrders();
        $this->disableHpos();
        parent::tearDown();
    }

    /**
     * Test handleUnsuccessfulPayment method with cancelled status
     */
    public function test_handle_unsuccessful_payment_with_cancelled_status()
    {
        $result = Helper::handleUnsuccessfulPayment(ResponseStatus::BUCKAROO_STATUSCODE_CANCELLED_BY_USER);
        $this->assertTrue($result);
    }

    /**
     * Test handleUnsuccessfulPayment method with rejected status
     */
    public function test_handle_unsuccessful_payment_with_rejected_status()
    {
        $result = Helper::handleUnsuccessfulPayment(ResponseStatus::BUCKAROO_STATUSCODE_REJECTED);
        $this->assertTrue($result);
    }

    /**
     * Test handleUnsuccessfulPayment method with successful status
     */
    public function test_handle_unsuccessful_payment_with_successful_status()
    {
        $result = Helper::handleUnsuccessfulPayment(ResponseStatus::BUCKAROO_STATUSCODE_SUCCESS);
        $this->assertFalse($result);
    }

    /**
     * Test roundAmount with valid numeric values
     */
    public function test_round_amount_with_valid_numbers()
    {
        $this->assertEquals(10.50, Helper::roundAmount(10.5));
        $this->assertEquals(10.00, Helper::roundAmount(10));
        $this->assertEquals(10.99, Helper::roundAmount(10.99));
        $this->assertEquals(10.12, Helper::roundAmount(10.123456));
    }

    /**
     * Test roundAmount with string numeric values
     */
    public function test_round_amount_with_string_numbers()
    {
        $this->assertEquals(10.50, Helper::roundAmount('10.5'));
        $this->assertEquals(10.00, Helper::roundAmount('10'));
        $this->assertEquals(10.99, Helper::roundAmount('10.99'));
    }

    /**
     * Test roundAmount with invalid values
     */
    public function test_round_amount_with_invalid_values()
    {
        $this->assertEquals(0, Helper::roundAmount('invalid'));
        $this->assertEquals(0, Helper::roundAmount(null));
        $this->assertEquals(0, Helper::roundAmount([]));
        $this->assertEquals(0, Helper::roundAmount(new stdClass()));
    }

    /**
     * Test roundAmount with edge cases
     */
    public function test_round_amount_edge_cases()
    {
        $this->assertEquals(0.00, Helper::roundAmount(0));
        $this->assertEquals(0.01, Helper::roundAmount(0.01));
        $this->assertEquals(0.99, Helper::roundAmount(0.99));
        $this->assertEquals(999999.99, Helper::roundAmount(999999.99));
    }

    /**
     * Test getAllGendersForPaymentMethods returns correct structure
     */
    public function test_get_all_genders_for_payment_methods()
    {
        $genders = Helper::getAllGendersForPaymentMethods();

        $this->assertIsArray($genders);
        $this->assertArrayHasKey('buckaroo-payperemail', $genders);
        $this->assertArrayHasKey('buckaroo-billink', $genders);
        $this->assertArrayHasKey('buckaroo-klarnakp', $genders);
        $this->assertArrayHasKey('buckaroo-klarnapay', $genders);
    }

    /**
     * Test getAllGendersForPaymentMethods default genders
     */
    public function test_get_all_genders_for_payment_methods_default_values()
    {
        $genders = Helper::getAllGendersForPaymentMethods();

        $this->assertEquals(1, $genders['buckaroo-payperemail']['male']);
        $this->assertEquals(2, $genders['buckaroo-payperemail']['female']);
        $this->assertEquals(0, $genders['buckaroo-payperemail']['they']);
        $this->assertEquals(9, $genders['buckaroo-payperemail']['unknown']);
    }

    /**
     * Test getAllGendersForPaymentMethods Billink genders
     */
    public function test_get_all_genders_for_payment_methods_billink_values()
    {
        $genders = Helper::getAllGendersForPaymentMethods();

        $this->assertEquals('Male', $genders['buckaroo-billink']['male']);
        $this->assertEquals('Female', $genders['buckaroo-billink']['female']);
        $this->assertEquals('Unknown', $genders['buckaroo-billink']['they']);
        $this->assertEquals('Unknown', $genders['buckaroo-billink']['unknown']);
    }

    /**
     * Test getAllGendersForPaymentMethods Klarna genders
     */
    public function test_get_all_genders_for_payment_methods_klarna_values()
    {
        $genders = Helper::getAllGendersForPaymentMethods();

        $this->assertEquals('male', $genders['buckaroo-klarnakp']['male']);
        $this->assertEquals('female', $genders['buckaroo-klarnakp']['female']);
        $this->assertArrayNotHasKey('they', $genders['buckaroo-klarnakp']);
        $this->assertArrayNotHasKey('unknown', $genders['buckaroo-klarnakp']);
    }

    /**
     * Test translateGender with valid keys
     */
    public function test_translate_gender_with_valid_keys()
    {
        $this->assertEquals(__('He/him', 'wc-buckaroo-bpe-gateway'), Helper::translateGender('male'));
        $this->assertEquals(__('She/her', 'wc-buckaroo-bpe-gateway'), Helper::translateGender('female'));
        $this->assertEquals(__('They/them', 'wc-buckaroo-bpe-gateway'), Helper::translateGender('they'));
        $this->assertEquals(__('I prefer not to say', 'wc-buckaroo-bpe-gateway'), Helper::translateGender('unknown'));
    }

    /**
     * Test translateGender with invalid key
     */
    public function test_translate_gender_with_invalid_key()
    {
        $invalidKey = 'invalid_gender';
        $this->assertEquals($invalidKey, Helper::translateGender($invalidKey));
    }

    /**
     * Test get method with null payment ID
     */
    public function test_get_with_null_payment_id()
    {
        // This test requires WordPress functions to be available
        if (!function_exists('get_option')) {
            $this->markTestSkipped('WordPress functions not available');
        }

        $result = Helper::get('some_key');
        $this->assertNull($result);
    }

    /**
     * Test isWooCommerceVersion3OrGreater
     */
    public function test_is_woocommerce_version_3_or_greater()
    {
        if (!function_exists('WC')) {
            $this->markTestSkipped('WooCommerce not available');
        }

        $result = Helper::isWooCommerceVersion3OrGreater();
        $this->assertIsBool($result);
    }

    /**
     * Test isOrderInstance with WC_Order
     */
    public function test_is_order_instance_with_wc_order()
    {
        if (!class_exists('WC_Order')) {
            $this->markTestSkipped('WC_Order class not available');
        }

        $order = $this->createMock(WC_Order::class);
        $this->assertTrue(Helper::isOrderInstance($order));
    }

    /**
     * Test isOrderInstance with non-order object
     */
    public function test_is_order_instance_with_non_order()
    {
        $this->assertFalse(Helper::isOrderInstance(new stdClass()));
        $this->assertFalse(Helper::isOrderInstance('string'));
        $this->assertFalse(Helper::isOrderInstance(123));
        $this->assertFalse(Helper::isOrderInstance([]));
    }

    /**
     * Test checkCreditCardProvider with invalid provider
     */
    public function test_check_credit_card_provider_with_invalid()
    {
        if (!class_exists('WC_Payment_Gateways')) {
            $this->markTestSkipped('WooCommerce payment gateways not available');
        }

        // Check if credit card gateway is loaded
        $gateways = WC_Payment_Gateways::instance()->payment_gateways();
        if (!isset($gateways['buckaroo_creditcard'])) {
            $this->markTestSkipped('Buckaroo credit card gateway not loaded in test environment');
        }

        // This will return false if the provider is invalid
        $result = Helper::checkCreditCardProvider('invalid_provider');
        $this->assertFalse($result);
    }

    /**
     * resetOrder used to read the status with get_post_status(), which returns
     * "draft" for the shop_order_placehold row under HPOS, so the branch never ran.
     */
    public function test_reset_order_cancels_a_failed_order_on_an_hpos_store()
    {
        $this->enableHpos();

        $order = $this->createOrder();
        $order->set_status('failed');
        $order->save();

        WC()->session->order_awaiting_payment = $order->get_id();

        Helper::resetOrder();

        $cancelled = wc_get_order($order->get_id());
        $this->assertSame('cancelled', $cancelled->get_status());
        $this->assertNotSame('', $cancelled->get_cart_hash(), 'The cart hash should have been rebuilt');
    }

    public function test_reset_order_leaves_an_order_in_any_other_status_alone()
    {
        $this->enableHpos();

        foreach (['pending', 'on-hold', 'processing', 'completed'] as $status) {
            $order = $this->createOrder();
            $order->set_status($status);
            $order->save();

            WC()->session->order_awaiting_payment = $order->get_id();

            Helper::resetOrder();

            $this->assertSame(
                $status,
                wc_get_order($order->get_id())->get_status(),
                "Order in status {$status} should not have been touched"
            );
        }
    }

    public function test_reset_order_cancels_an_already_cancelled_order()
    {
        $this->enableHpos();

        $order = $this->createOrder();
        $order->set_status('cancelled');
        $order->save();

        WC()->session->order_awaiting_payment = $order->get_id();

        Helper::resetOrder();

        $this->assertSame('cancelled', wc_get_order($order->get_id())->get_status());
    }

    public function test_reset_order_handles_a_session_order_that_no_longer_exists()
    {
        $this->enableHpos();

        WC()->session->order_awaiting_payment = 999999999;

        Helper::resetOrder();

        $this->assertFalse(wc_get_order(999999999));
    }
}
