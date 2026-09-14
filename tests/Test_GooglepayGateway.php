<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\Googlepay\GooglepayGateway;
use PHPUnit\Framework\TestCase;

/**
 * Test Google Pay Gateway
 */
class Test_GooglepayGateway extends TestCase
{
    /**
     * Map a Google Pay contact through the gateway's private mapper.
     */
    private function orderAddresses(array $contact): array
    {
        if (! class_exists('WC_Payment_Gateway')) {
            $this->markTestSkipped('WooCommerce not available');
        }

        $method = new ReflectionMethod(GooglepayGateway::class, 'orderAddresses');
        $method->setAccessible(true);

        return $method->invoke(null, $contact);
    }

    /**
     * A FULL format Google Pay contact fills every WooCommerce address field.
     */
    public function test_order_addresses_maps_a_complete_contact()
    {
        $address = $this->orderAddresses(
            [
                'givenName' => 'Jane',
                'familyName' => 'Doe',
                'emailAddress' => 'jane@example.com',
                'phoneNumber' => '+31612345678',
                'addressLines' => ['Kerkstraat 42', 'Apartment 3', 'Building B'],
                'locality' => 'Amsterdam',
                'administrativeArea' => 'NH',
                'postalCode' => '1017 GB',
                'countryCode' => 'NL',
            ]
        );

        $this->assertEquals(
            [
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'email' => 'jane@example.com',
                'phone' => '+31612345678',
                'address_1' => 'Kerkstraat 42',
                'address_2' => 'Apartment 3, Building B',
                'city' => 'Amsterdam',
                'state' => 'NH',
                'postcode' => '1017 GB',
                'country' => 'NL',
            ],
            $address
        );
    }

    /**
     * A single address line leaves the second street field empty.
     */
    public function test_order_addresses_leaves_address_2_empty_for_a_single_line()
    {
        $address = $this->orderAddresses(
            [
                'givenName' => 'Jane',
                'familyName' => 'Doe',
                'emailAddress' => 'jane@example.com',
                'phoneNumber' => '',
                'addressLines' => ['Kerkstraat 42'],
                'locality' => 'Amsterdam',
                'administrativeArea' => '',
                'postalCode' => '1017 GB',
                'countryCode' => 'NL',
            ]
        );

        $this->assertEquals('Kerkstraat 42', $address['address_1']);
        $this->assertEquals('', $address['address_2']);
        $this->assertEquals('', $address['state']);
        $this->assertEquals('', $address['phone']);
    }

    /**
     * An empty contact maps to empty fields instead of raising a notice. This is
     * the shape Google Pay returned before the billing address was requested.
     */
    public function test_order_addresses_handles_an_empty_contact()
    {
        $address = $this->orderAddresses(
            [
                'givenName' => '',
                'familyName' => '',
                'addressLines' => [''],
                'locality' => '',
                'postalCode' => '',
                'countryCode' => '',
            ]
        );

        $this->assertEquals(
            [
                'first_name' => '',
                'last_name' => '',
                'email' => '',
                'phone' => '',
                'address_1' => '',
                'address_2' => '',
                'city' => '',
                'state' => '',
                'postcode' => '',
                'country' => '',
            ],
            $address
        );
    }
}
