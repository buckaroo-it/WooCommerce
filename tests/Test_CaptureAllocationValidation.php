<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Order\CaptureAllocation;
use PHPUnit\Framework\TestCase;

class Test_CaptureAllocationValidation extends TestCase
{
    private function available(): CaptureAllocation
    {
        // Item 11: 2 x 25.00, item 12: shipping 5.00, item 13: discount fee -3.00
        return CaptureAllocation::fromArrays(
            [11 => 2, 12 => 1, 13 => 1],
            [11 => 50.00, 12 => 5.00, 13 => -3.00],
            [11 => ['1' => '8.68'], 12 => [], 13 => []]
        );
    }

    public function test_full_allocation_is_within_the_available_items(): void
    {
        $this->assertTrue($this->available()->isWithin($this->available()));
    }

    public function test_partial_allocation_is_within_the_available_items(): void
    {
        $requested = CaptureAllocation::fromArrays([11 => 1], [11 => 25.00], [11 => []]);

        $this->assertTrue($requested->isWithin($this->available()));
    }

    public function test_allocation_from_the_capture_form_with_string_values_is_accepted(): void
    {
        $requested = CaptureAllocation::fromJson(
            wp_json_encode(['11' => '2']),
            wp_json_encode(['11' => 50, '12' => 5, '13' => -3]),
            '{}'
        );

        $this->assertTrue($requested->isWithin($this->available()));
    }

    public function test_negative_item_total_is_rejected(): void
    {
        $requested = CaptureAllocation::fromArrays(
            [11 => 2, 12 => 1],
            [11 => 50.00, 12 => -5.00],
            []
        );

        $this->assertFalse($requested->isWithin($this->available()));
    }

    public function test_negative_quantity_is_rejected(): void
    {
        $requested = CaptureAllocation::fromArrays([11 => -1], [11 => 0.00], []);

        $this->assertFalse($requested->isWithin($this->available()));
    }

    public function test_fractional_quantity_is_rejected(): void
    {
        $requested = CaptureAllocation::fromArrays([11 => 1.5], [11 => 37.50], []);

        $this->assertFalse($requested->isWithin($this->available()));
    }

    public function test_item_of_another_order_is_rejected(): void
    {
        $requested = CaptureAllocation::fromArrays([99 => 1], [99 => 1.00], []);

        $this->assertFalse($requested->isWithin($this->available()));
    }

    public function test_quantity_for_an_item_of_another_order_is_rejected(): void
    {
        $requested = CaptureAllocation::fromArrays([11 => 1, 99 => 1], [11 => 25.00], []);

        $this->assertFalse($requested->isWithin($this->available()));
    }

    /**
     * @dataProvider malformedAllocations
     */
    public function test_malformed_allocation_is_rejected(array $quantities, array $totals, array $taxTotals): void
    {
        $requested = CaptureAllocation::fromArrays($quantities, $totals, $taxTotals);

        $this->assertFalse($requested->isWithin($this->available()));
    }

    public static function malformedAllocations(): array
    {
        return [
            'non-numeric total' => [[11 => 1], [11 => 'abc'], []],
            'array total' => [[11 => 1], [11 => [25]], []],
            'non-numeric quantity' => [[11 => 'one'], [11 => 25.00], []],
            'non-numeric item id' => [['x' => 1], ['x' => 25.00], []],
            'zero item id' => [[0 => 1], [0 => 25.00], []],
            'tax totals not an array' => [[11 => 1], [11 => 25.00], [11 => '4.34']],
            'non-numeric tax' => [[11 => 1], [11 => 25.00], [11 => ['1' => 'x']]],
            'infinite total' => [[11 => 1], [11 => INF], []],
        ];
    }

    public function test_total_above_the_item_amount_is_rejected(): void
    {
        $requested = CaptureAllocation::fromArrays([11 => 2], [11 => 50.01], []);

        $this->assertFalse($requested->isWithin($this->available()));
    }

    public function test_quantity_above_the_item_quantity_is_rejected(): void
    {
        $requested = CaptureAllocation::fromArrays([11 => 3], [11 => 50.00], []);

        $this->assertFalse($requested->isWithin($this->available()));
    }

    public function test_discount_fee_is_accepted_up_to_its_own_amount(): void
    {
        $requested = CaptureAllocation::fromArrays([11 => 2], [11 => 50.00, 13 => -3.00], []);

        $this->assertTrue($requested->isWithin($this->available()));
    }

    public function test_discount_fee_beyond_its_own_amount_is_rejected(): void
    {
        $requested = CaptureAllocation::fromArrays([11 => 2], [11 => 50.00, 13 => -4.00], []);

        $this->assertFalse($requested->isWithin($this->available()));
    }

    public function test_discount_fee_with_a_positive_amount_is_rejected(): void
    {
        $requested = CaptureAllocation::fromArrays([11 => 2], [11 => 50.00, 13 => 3.00], []);

        $this->assertFalse($requested->isWithin($this->available()));
    }

    public function test_amounts_are_compared_in_cents(): void
    {
        $available = CaptureAllocation::fromArrays([11 => 3], [11 => 0.30], []);
        $requested = CaptureAllocation::fromArrays([11 => 3], [11 => 0.1 + 0.2], []);

        $this->assertTrue($requested->isWithin($available));
    }
}
