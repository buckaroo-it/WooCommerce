<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Services\PaymentFee;
use PHPUnit\Framework\TestCase;

class Test_PaymentFee extends TestCase
{
    public function test_combined_fee_uses_both_components_and_rounds_once(): void
    {
        $fee = PaymentFee::parse('0.25 + 1.5%');
        $this->assertNotNull($fee);
        $this->assertSame(1.75, $fee->calculate(100));
        $this->assertSame(1.45, $fee->calculate(80));
        $this->assertSame(0.01, PaymentFee::parse('0.004 + 0.4%')->calculate(1));
    }

    /** @dataProvider validFees */
    public function test_existing_and_zero_component_fees($raw, float $base, float $expected): void
    {
        $this->assertSame($expected, PaymentFee::parse($raw)->calculate($base));
    }

    public function validFees(): array
    {
        return [
            ['2.505', 100, 2.505], ['1.5%', 80, 1.2],
            ['0 + 1.5%', 100, 1.5], ['0.25 + 0%', 100, 0.25],
            [' 0.25+1.5% ', 100, 1.75], ['0 + 0%', 100, 0.0],
            ['0.25 + 1.5%', 0, 0.25], [0, 100, 0.0],
        ];
    }

    /** @dataProvider invalidFees */
    public function test_invalid_settings_are_rejected($raw): void
    {
        $this->assertNull(PaymentFee::parse($raw));
    }

    public function invalidFees(): array
    {
        return array_map(static function ($raw) { return [$raw]; }, [
            '', null, [], new stdClass(), '-1', '1 + -2%', '1,5',
            '1.5% + 0.25', '1 + 2% + 3', '1 + 2', '1e3', 'NaN',
            str_repeat('9', 400), "1 + 2% garbage",
        ]);
    }
}
