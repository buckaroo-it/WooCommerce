<?php

namespace Buckaroo\Woocommerce\Order;

use Buckaroo\Woocommerce\Services\Helper;
use WC_Order;

class CaptureAllocation
{
    private array $quantities;

    private array $totals;

    private array $taxTotals;

    private function __construct(array $quantities, array $totals, array $taxTotals)
    {
        $this->quantities = $quantities;
        $this->totals = $totals;
        $this->taxTotals = $taxTotals;
    }

    public static function fromArrays(array $quantities, array $totals, array $taxTotals): self
    {
        return new self($quantities, $totals, $taxTotals);
    }

    public static function fromJson($quantities, $totals, $taxTotals): self
    {
        return new self(
            self::decode($quantities),
            self::decode($totals),
            self::decode($taxTotals)
        );
    }

    public static function forOrder(WC_Order $order): self
    {
        $quantities = [];
        $totals = [];
        $taxTotals = [];

        foreach ($order->get_items(['line_item', 'shipping', 'fee']) as $item) {
            $itemId = $item->get_id();
            $quantity = $item->get_type() === 'line_item' ? (int) $item->get_quantity() : 1;
            $quantities[$itemId] = $quantity;
            $totals[$itemId] = Helper::roundAmount($order->get_item_total($item, true) * $quantity);
            $taxes = method_exists($item, 'get_taxes') ? $item->get_taxes() : [];
            $taxTotals[$itemId] = is_array($taxes) && isset($taxes['total']) ? $taxes['total'] : [];
        }

        if (! empty($totals)) {
            $difference = Helper::roundAmount((float) $order->get_total('edit') - array_sum($totals));
            if (abs($difference) >= 0.01) {
                $lastItemId = array_key_last($totals);
                $totals[$lastItemId] = Helper::roundAmount($totals[$lastItemId] + $difference);
            }
        }

        return new self($quantities, $totals, $taxTotals);
    }

    public static function remainingForOrder(WC_Order $order, ?float $maximumAmount = null): self
    {
        $current = self::forOrder($order);
        $quantities = $current->getQuantities();
        $totals = $current->getTotals();
        $taxTotals = $current->getTaxTotals();
        $capturedAmount = 0.0;

        foreach (OrderMeta::get($order, '_wc_order_captures', false) as $capture) {
            if (! is_array($capture)) {
                continue;
            }

            $captureAmount = isset($capture['amount']) && is_numeric($capture['amount'])
                ? (float) $capture['amount']
                : 0.0;
            $capturedAmount += $captureAmount;
            $capturedTotals = self::decodeLedgerValue($capture['line_item_totals'] ?? null);
            $capturedQuantities = self::decodeLedgerValue($capture['line_item_qtys'] ?? null);
            $capturedTaxTotals = self::decodeLedgerValue($capture['line_item_tax_totals'] ?? null);

            foreach ($capturedTotals as $itemId => $total) {
                if (isset($totals[$itemId]) && is_numeric($total)) {
                    $totals[$itemId] = Helper::roundAmount(max(0, $totals[$itemId] - (float) $total));
                }
            }

            foreach ($capturedQuantities as $itemId => $quantity) {
                if (isset($quantities[$itemId]) && is_numeric($quantity)) {
                    $quantities[$itemId] = max(0, $quantities[$itemId] - (int) $quantity);
                }
            }

            foreach ($capturedTaxTotals as $itemId => $capturedTaxes) {
                if (! isset($taxTotals[$itemId]) || ! is_array($capturedTaxes)) {
                    continue;
                }

                foreach ($capturedTaxes as $taxId => $capturedTax) {
                    if (isset($taxTotals[$itemId][$taxId]) && is_numeric($capturedTax)) {
                        $taxTotals[$itemId][$taxId] = max(
                            0,
                            (float) $taxTotals[$itemId][$taxId] - (float) $capturedTax
                        );
                    }
                }
            }

            $unallocatedAmount = Helper::roundAmount($captureAmount - array_sum($capturedTotals));
            if ($unallocatedAmount > 0) {
                self::reduceTotals($totals, $quantities, $taxTotals, $unallocatedAmount);
            }
        }

        if ($maximumAmount !== null) {
            $maximumRemaining = Helper::roundAmount(max(0, $maximumAmount - $capturedAmount));
            $excess = Helper::roundAmount(array_sum($totals) - $maximumRemaining);
            if ($excess > 0) {
                self::reduceTotals($totals, $quantities, $taxTotals, $excess, true);
            }
        }

        return new self($quantities, $totals, $taxTotals);
    }

    private static function decodeLedgerValue($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function reduceTotals(
        array &$totals,
        array &$quantities,
        array &$taxTotals,
        float $amount,
        bool $fromEnd = false
    ): void {
        $itemIds = array_keys($totals);
        if ($fromEnd) {
            $itemIds = array_reverse($itemIds);
        }

        foreach ($itemIds as $itemId) {
            if ($amount <= 0) {
                break;
            }

            $currentTotal = (float) $totals[$itemId];
            if ($currentTotal <= 0) {
                continue;
            }

            $reduction = min($currentTotal, $amount);
            $remainingRatio = ($currentTotal - $reduction) / $currentTotal;
            $totals[$itemId] = Helper::roundAmount($currentTotal - $reduction);
            $amount = Helper::roundAmount($amount - $reduction);

            if ($totals[$itemId] <= 0) {
                $quantities[$itemId] = 0;
            }

            if (isset($taxTotals[$itemId]) && is_array($taxTotals[$itemId])) {
                foreach ($taxTotals[$itemId] as $taxId => $taxTotal) {
                    if (is_numeric($taxTotal)) {
                        $taxTotals[$itemId][$taxId] = (float) $taxTotal * $remainingRatio;
                    }
                }
            }
        }
    }

    private static function decode($value): array
    {
        if (! is_string($value)) {
            return [];
        }

        $decoded = json_decode(wp_unslash($value), true);

        return is_array($decoded) ? map_deep($decoded, 'sanitize_text_field') : [];
    }

    public function getQuantities(): array
    {
        return $this->quantities;
    }

    public function getTotals(): array
    {
        return $this->totals;
    }

    public function getTaxTotals(): array
    {
        return $this->taxTotals;
    }

    public function getQuantity(int $itemId): int
    {
        return max(0, (int) ($this->quantities[$itemId] ?? 1));
    }

    public function getTotal(int $itemId): float
    {
        return (float) ($this->totals[$itemId] ?? 0);
    }

    public function getAmount(): float
    {
        return (float) array_sum($this->totals);
    }

    public function toLedger(): array
    {
        return [
            'line_item_qtys' => wp_json_encode($this->quantities),
            'line_item_totals' => wp_json_encode($this->totals),
            'line_item_tax_totals' => wp_json_encode($this->taxTotals),
        ];
    }

    public function fingerprint(): string
    {
        $quantities = $this->quantities;
        $totals = $this->totals;
        $taxTotals = $this->taxTotals;
        ksort($quantities);
        ksort($totals);
        ksort($taxTotals);

        return hash('sha256', wp_json_encode([$quantities, $totals, $taxTotals]));
    }

    public function isWithin(self $available): bool
    {
        if ($this->getAmount() - $available->getAmount() >= 0.01) {
            return false;
        }

        foreach ($this->totals as $itemId => $total) {
            if ((float) $total - $available->getTotal((int) $itemId) >= 0.01) {
                return false;
            }

            if ($this->getQuantity((int) $itemId) > $available->getQuantity((int) $itemId)) {
                return false;
            }
        }

        return true;
    }

    public function overlaps(self $other): bool
    {
        $itemIds = array_unique(array_merge(
            array_keys($this->totals),
            array_keys($this->quantities),
            array_keys($other->totals),
            array_keys($other->quantities)
        ));
        foreach ($itemIds as $itemId) {
            $hasAllocation = abs((float) ($this->totals[$itemId] ?? 0)) >= 0.01 ||
                (int) ($this->quantities[$itemId] ?? 0) > 0;
            $otherHasAllocation = abs((float) ($other->totals[$itemId] ?? 0)) >= 0.01 ||
                (int) ($other->quantities[$itemId] ?? 0) > 0;
            if ($hasAllocation && $otherHasAllocation) {
                return true;
            }
        }

        return false;
    }
}
