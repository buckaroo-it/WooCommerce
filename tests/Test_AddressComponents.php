<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Order\AddressComponents;
use PHPUnit\Framework\TestCase;

/**
 * Test AddressComponents class
 */
class Test_AddressComponents extends TestCase
{
    /**
     * Test constructor and basic parsing
     */
    public function test_constructor_parses_address()
    {
        $address = new AddressComponents('Main Street 123');

        $this->assertEquals('Main Street', $address->get_street());
        $this->assertEquals('123', $address->get_house_number());
        $this->assertEquals('', $address->get_number_additional());
    }

    /**
     * Test parsing address with number addition
     */
    public function test_parse_address_with_addition()
    {
        $address = new AddressComponents('Main Street 123 A');

        $this->assertEquals('Main Street', $address->get_street());
        $this->assertEquals('123', $address->get_house_number());
        $this->assertEquals('A', $address->get_number_additional());
    }

    /**
     * Test parsing address with complex addition
     */
    public function test_parse_address_with_complex_addition()
    {
        $address = new AddressComponents('Main Street 123-125');

        $this->assertEquals('Main Street', $address->get_street());
        $this->assertEquals('123', $address->get_house_number());
        $this->assertEquals('-125', $address->get_number_additional());
    }

    /**
     * Test parsing Dutch address format
     */
    public function test_parse_dutch_address_format()
    {
        $address = new AddressComponents('Kerkstraat 42 bis');

        $this->assertEquals('Kerkstraat', $address->get_street());
        $this->assertEquals('42', $address->get_house_number());
        $this->assertEquals('bis', $address->get_number_additional());
    }

    /**
     * Test parsing address with leading number in street name
     */
    public function test_parse_address_with_leading_number()
    {
        $address = new AddressComponents('1e Straat 10');

        $this->assertEquals('1e Straat', $address->get_street());
        $this->assertEquals('10', $address->get_house_number());
        $this->assertEquals('', $address->get_number_additional());
    }

    /**
     * Test parsing address with leading number and addition
     */
    public function test_parse_address_with_leading_number_and_addition()
    {
        $address = new AddressComponents('2e Dwarsstraat 25 A');

        $this->assertEquals('2e Dwarsstraat', $address->get_street());
        $this->assertEquals('25', $address->get_house_number());
        $this->assertEquals('A', $address->get_number_additional());
    }

    /**
     * Test parsing address without house number
     */
    public function test_parse_address_without_house_number()
    {
        $address = new AddressComponents('Main Street');

        $this->assertEquals('Main Street', $address->get_street());
        $this->assertEquals('', $address->get_house_number());
        $this->assertEquals('', $address->get_number_additional());
    }

    /**
     * Test parsing address with special characters
     */
    public function test_parse_address_with_special_characters()
    {
        $address = new AddressComponents('Main Street? 123! [A]');

        $this->assertEquals('Main Street', $address->get_street());
        $this->assertEquals('123', $address->get_house_number());
        $this->assertEquals('A', $address->get_number_additional());
    }

    /**
     * Test special characters are removed
     */
    public function test_special_characters_removed()
    {
        $address = new AddressComponents('Main*Street, 123');

        $this->assertEquals('Main Street', $address->get_street());
        $this->assertEquals('123', $address->get_house_number());
    }

    /**
     * Test multiple spaces are collapsed
     */
    public function test_multiple_spaces_collapsed()
    {
        $address = new AddressComponents('Main    Street   123');

        $this->assertEquals('Main Street', $address->get_street());
        $this->assertEquals('123', $address->get_house_number());
    }

    /**
     * Test empty address
     */
    public function test_empty_address()
    {
        $address = new AddressComponents('');

        $this->assertEquals('', $address->get_street());
        $this->assertEquals('', $address->get_house_number());
        $this->assertEquals('', $address->get_number_additional());
    }

    /**
     * Test address with only numbers
     */
    public function test_address_with_only_numbers()
    {
        $address = new AddressComponents('123');

        // When only numbers, it's treated as street name
        $this->assertEquals('123', $address->get_street());
        $this->assertEquals('', $address->get_house_number());
    }

    /**
     * Test get_house_number method
     */
    public function test_get_house_number_method()
    {
        $address = new AddressComponents('Test Street 456');

        $result = $address->get_house_number();

        $this->assertEquals('456', $result);
    }

    /**
     * Test get_number_additional method
     */
    public function test_get_number_additional_method()
    {
        $address = new AddressComponents('Test Street 456 B');

        $result = $address->get_number_additional();

        $this->assertEquals('B', $result);
    }

    /**
     * Test get_street method
     */
    public function test_get_street_method()
    {
        $address = new AddressComponents('Test Street 456');

        $result = $address->get_street();

        $this->assertEquals('Test Street', $result);
    }

    /**
     * Test parsing complex real-world Dutch addresses
     */
    public function test_complex_dutch_addresses()
    {
        // Address with dash in number addition
        $address1 = new AddressComponents('Hoofdstraat 12-14');
        $this->assertEquals('Hoofdstraat', $address1->get_street());
        $this->assertEquals('12', $address1->get_house_number());
        $this->assertEquals('-14', $address1->get_number_additional());

        // Address with Roman numeral addition
        $address2 = new AddressComponents('Kerkweg 5 II');
        $this->assertEquals('Kerkweg', $address2->get_street());
        $this->assertEquals('5', $address2->get_house_number());
        $this->assertEquals('II', $address2->get_number_additional());
    }

    /**
     * Test address components are trimmed
     */
    public function test_address_components_trimmed()
    {
        $address = new AddressComponents('  Main Street   123   A  ');

        $this->assertEquals('Main Street', $address->get_street());
        $this->assertEquals('123', $address->get_house_number());
        $this->assertEquals('A', $address->get_number_additional());
    }

    /**
     * Test address with hyphenated street name
     */
    public function test_address_with_hyphenated_street()
    {
        $address = new AddressComponents('Wilhelmina-straat 42');

        $this->assertEquals('Wilhelmina-straat', $address->get_street());
        $this->assertEquals('42', $address->get_house_number());
        $this->assertEquals('', $address->get_number_additional());
    }

    /**
     * Test address with apartment number
     */
    public function test_address_with_apartment_number()
    {
        $address = new AddressComponents('Main Street 123 apt 4B');

        $this->assertEquals('Main Street', $address->get_street());
        $this->assertEquals('123', $address->get_house_number());
        $this->assertEquals('apt 4B', $address->get_number_additional());
    }

    /**
     * Test edge case with street starting with number
     */
    public function test_street_starting_with_number()
    {
        $address = new AddressComponents('123rd Avenue 45');

        $this->assertEquals('123rd Avenue', $address->get_street());
        $this->assertEquals('45', $address->get_house_number());
        $this->assertEquals('', $address->get_number_additional());
    }

    /**
     * Test address with lowercase letters in addition
     */
    public function test_address_with_lowercase_addition()
    {
        $address = new AddressComponents('Main Street 123 b');

        $this->assertEquals('Main Street', $address->get_street());
        $this->assertEquals('123', $address->get_house_number());
        $this->assertEquals('b', $address->get_number_additional());
    }
}
