<?php

namespace Buckaroo\Woocommerce\Services;

use WC_Tax;

/** A configured fixed, percentage, or fixed-plus-percentage payment fee. */
class PaymentFee
{
    public float $fixed;

    public float $percentage;

    public bool $combined;

    public bool $percentageFirst;

    private function __construct(float $fixed, float $percentage, bool $combined, bool $percentageFirst)
    {
        $this->fixed = $fixed;
        $this->percentage = $percentage;
        $this->combined = $combined;
        $this->percentageFirst = $percentageFirst;
    }

    public static function parse($value): ?self
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);
        if (! preg_match('/\A(\d+(?:\.\d+)?)(%?)(?:\s*\+\s*(\d+(?:\.\d+)?)(%?))?\z/', $value, $parts)) {
            return null;
        }

        $combined = isset($parts[3]);
        if ($combined && $parts[2] === $parts[4]) {
            return null;
        }

        $percentageFirst = $parts[2] === '%';
        $fixed = $percentageFirst ? (float) ($parts[3] ?? 0) : (float) $parts[1];
        $percentage = $percentageFirst ? (float) $parts[1] : (float) ($parts[3] ?? 0);
        if (! is_finite($fixed) || ! is_finite($percentage)) {
            return null;
        }

        return new self($fixed, $percentage, $combined, $percentageFirst);
    }

    public function calculate(float $base): float
    {
        if ($this->combined && ! $this->percentageFirst) {
            $base += $this->fixed;
        }
        $amount = $this->fixed + $base * $this->percentage / 100;
        if (! is_finite($amount)) {
            return 0.0;
        }

        return $this->combined || $this->percentage > 0 ? round($amount, 2) : $amount;
    }

    public function hasFee(): bool
    {
        return $this->fixed > 0 || $this->percentage > 0;
    }

    /** Return the net amount WooCommerce needs when adding a taxable fee. */
    public function calculateExcludingTax(float $base, array $taxRates): float
    {
        $amount = $this->calculate($base);

        return wc_prices_include_tax()
            ? $amount - array_sum(WC_Tax::calc_tax($amount, $taxRates, true))
            : $amount;
    }
}
