<?php

namespace Buckaroo\Woocommerce\Services;

/** A configured fixed, percentage, or fixed-plus-percentage payment fee. */
class PaymentFee
{
    public float $fixed;

    public float $percentage;

    public bool $combined;

    private function __construct(float $fixed, float $percentage, bool $combined)
    {
        $this->fixed = $fixed;
        $this->percentage = $percentage;
        $this->combined = $combined;
    }

    public static function parse($value): ?self
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);
        if (! preg_match('/\A(\d+(?:\.\d+)?)(?:(%)|\s*\+\s*(\d+(?:\.\d+)?)%)?\z/', $value, $parts)) {
            return null;
        }

        $combined = isset($parts[3]);
        $fixed = ($parts[2] ?? '') === '%' ? 0.0 : (float) $parts[1];
        $percentage = $combined ? (float) $parts[3] : (($parts[2] ?? '') === '%' ? (float) $parts[1] : 0.0);
        if (! is_finite($fixed) || ! is_finite($percentage)) {
            return null;
        }

        return new self($fixed, $percentage, $combined);
    }

    public function calculate(float $base): float
    {
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
}
