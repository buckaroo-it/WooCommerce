<?php

namespace Buckaroo\Woocommerce\Order;

use WC_Order;

class KlarnaCaptureAttempt
{
    public const STALE_AFTER = 900;

    public const META_KEY = '_buckaroo_klarna_capture_attempts';

    public const ACTIVE_ATTENTION_META_KEY = '_buckaroo_klarna_capture_attention';

    public const NOTIFICATIONS_OPTION = 'buckaroo_klarna_capture_notifications';

    private const BLOCKING_STATES = ['queued', 'in_progress', 'pending', 'unknown'];

    public static function all(WC_Order $order): array
    {
        $order->read_meta_data(true);
        $attempts = OrderMeta::get($order, self::META_KEY);

        return is_array($attempts) ? $attempts : [];
    }

    public static function queue(WC_Order $order, CaptureAllocation $allocation): ?array
    {
        $fingerprint = $allocation->fingerprint();
        $claimKey = self::claimKey($order, $fingerprint);

        if (! self::acquireLock('attempt_ledger', $order, 'all')) {
            return null;
        }

        try {
            $attempts = self::all($order);
            if (
                self::hasOverlappingAttempt($attempts, $allocation) ||
                ! add_option($claimKey, ['state' => 'queued'], '', 'no')
            ) {
                return null;
            }

            $attemptNumber = count($attempts) + 1;
            update_option($claimKey, ['state' => 'queued', 'attempt_number' => $attemptNumber], false);

            $attempt = self::newAttempt($order, $allocation, $attemptNumber, 'queued', 'automatic');
            $attempts[] = $attempt;

            if (! OrderMeta::update($order, self::META_KEY, $attempts)) {
                delete_option($claimKey);

                return null;
            }

            return $attempt;
        } finally {
            self::releaseLock('attempt_ledger', $order, 'all');
        }
    }

    public static function startManual(WC_Order $order, CaptureAllocation $allocation): ?array
    {
        $fingerprint = $allocation->fingerprint();

        if (! self::acquireLock('attempt_ledger', $order, 'all')) {
            return null;
        }

        try {
            $attempts = self::all($order);
            if (
                self::hasOverlappingAttempt($attempts, $allocation) ||
                ! add_option(
                    self::claimKey($order, $fingerprint),
                    ['state' => 'in_progress'],
                    '',
                    'no'
                )
            ) {
                return null;
            }

            $attemptNumber = count($attempts) + 1;
            update_option(
                self::claimKey($order, $fingerprint),
                ['state' => 'in_progress', 'attempt_number' => $attemptNumber],
                false
            );

            $attempt = self::newAttempt($order, $allocation, $attemptNumber, 'in_progress', 'manual');
            $attempts[] = $attempt;
            if (! OrderMeta::update($order, self::META_KEY, $attempts)) {
                self::releaseClaim($order, $fingerprint);

                return null;
            }

            return $attempt;
        } finally {
            self::releaseLock('attempt_ledger', $order, 'all');
        }
    }

    public static function recordSkipped(WC_Order $order, CaptureAllocation $allocation, string $reason): array
    {
        $fingerprint = $allocation->fingerprint();
        if (! self::acquireLock('attempt_ledger', $order, 'all')) {
            return [];
        }

        try {
            $attempts = self::all($order);
            foreach ($attempts as $attempt) {
                if (
                    ($attempt['state'] ?? '') === 'skipped' &&
                    ($attempt['allocation_fingerprint'] ?? '') === $fingerprint &&
                    ($attempt['last_error'] ?? '') === $reason
                ) {
                    return $attempt;
                }
            }

            $attemptNumber = count($attempts) + 1;
            $claimed = add_option(
                self::claimKey($order, $fingerprint),
                ['state' => 'skipped', 'attempt_number' => $attemptNumber],
                '',
                'no'
            );
            if (! $claimed) {
                return [];
            }

            $attempt = self::newAttempt($order, $allocation, $attemptNumber, 'skipped', 'automatic');
            $attempt['last_error'] = $reason;
            $attempts[] = $attempt;
            OrderMeta::update($order, self::META_KEY, $attempts);
            $order->add_order_note($reason);
            self::releaseClaim($order, $fingerprint);

            return $attempt;
        } finally {
            self::releaseLock('attempt_ledger', $order, 'all');
        }
    }

    public static function claim(WC_Order $order, int $attemptNumber): ?array
    {
        if (! self::acquireLock('attempt_ledger', $order, 'all')) {
            return null;
        }

        try {
            $attempts = self::all($order);
            foreach ($attempts as $index => $attempt) {
                if ((int) ($attempt['attempt_number'] ?? 0) !== $attemptNumber) {
                    continue;
                }
                if ($attempt['state'] !== 'queued') {
                    return null;
                }
                if (! add_option(self::workerClaimKey($order, $attemptNumber), 'claimed', '', 'no')) {
                    return null;
                }

                $attempts[$index] = array_merge($attempt, [
                    'state' => 'in_progress',
                    'updated_at' => gmdate('c'),
                ]);
                if (! OrderMeta::update($order, self::META_KEY, $attempts)) {
                    self::releaseWorkerClaim($order, $attemptNumber);

                    return null;
                }

                return $attempts[$index];
            }

            return null;
        } finally {
            self::releaseLock('attempt_ledger', $order, 'all');
        }
    }

    public static function recoverStale(WC_Order $order, int $attemptNumber): ?array
    {
        $attempt = self::find($order, $attemptNumber);
        if ($attempt === null || ($attempt['state'] ?? '') !== 'in_progress') {
            return null;
        }

        $updatedAt = strtotime((string) ($attempt['updated_at'] ?? ''));
        if ($updatedAt === false || $updatedAt > time() - self::STALE_AFTER) {
            return null;
        }

        $recovered = self::updateUnlessSucceeded(
            $order,
            $attemptNumber,
            [
                'state' => 'unknown',
                'last_error' => __('The capture worker stopped before recording an outcome; reconciliation is required.', 'wc-buckaroo-bpe-gateway'),
            ]
        );
        self::releaseWorkerClaim($order, $attemptNumber);
        if ($recovered !== null && ($recovered['state'] ?? '') === 'unknown') {
            self::recordAttention($order, $recovered);
        }

        return $recovered;
    }

    public static function update(WC_Order $order, int $attemptNumber, array $changes): ?array
    {
        return self::mutateAttempt($order, $attemptNumber, $changes, false);
    }

    public static function updateUnlessSucceeded(WC_Order $order, int $attemptNumber, array $changes): ?array
    {
        $updated = self::mutateAttempt($order, $attemptNumber, $changes, true);
        if ($updated === null) {
            return null;
        }

        $state = $updated['state'] ?? '';
        if (in_array($state, ['succeeded', 'skipped'], true)) {
            self::releaseClaim($order, (string) $updated['allocation_fingerprint']);
        }
        if (in_array($state, ['succeeded', 'failed', 'skipped', 'pending', 'unknown'], true)) {
            self::releaseWorkerClaim($order, $attemptNumber);
        }

        return $updated;
    }

    private static function mutateAttempt(
        WC_Order $order,
        int $attemptNumber,
        array $changes,
        bool $preserveSuccess
    ): ?array {
        if (! self::acquireLock('attempt_ledger', $order, 'all')) {
            return null;
        }

        try {
            $attempts = self::all($order);
            foreach ($attempts as $index => $attempt) {
                if ((int) ($attempt['attempt_number'] ?? 0) !== $attemptNumber) {
                    continue;
                }
                if (
                    $preserveSuccess &&
                    ($attempt['state'] ?? '') === 'succeeded' &&
                    ($changes['state'] ?? '') !== 'succeeded'
                ) {
                    return $attempt;
                }

                $attempts[$index] = array_merge($attempt, $changes, ['updated_at' => gmdate('c')]);
                OrderMeta::update($order, self::META_KEY, $attempts);

                return $attempts[$index];
            }

            return null;
        } finally {
            self::releaseLock('attempt_ledger', $order, 'all');
        }
    }

    public static function find(WC_Order $order, int $attemptNumber): ?array
    {
        foreach (self::all($order) as $attempt) {
            if ((int) ($attempt['attempt_number'] ?? 0) === $attemptNumber) {
                return $attempt;
            }
        }

        return null;
    }

    public static function releaseClaim(WC_Order $order, string $fingerprint): void
    {
        delete_option(self::claimKey($order, $fingerprint));
    }

    public static function releaseWorkerClaim(WC_Order $order, int $attemptNumber): void
    {
        delete_option(self::workerClaimKey($order, $attemptNumber));
    }

    public static function latest(WC_Order $order): ?array
    {
        $attempts = self::all($order);

        return empty($attempts) ? null : end($attempts);
    }

    public static function canRetry(WC_Order $order): bool
    {
        $attempt = self::latest($order);
        if ($attempt === null || $attempt['state'] !== 'failed') {
            return false;
        }

        $reservedAmount = $order->get_meta('_buckaroo_klarna_reserved_amount');
        $remaining = CaptureAllocation::remainingForOrder(
            $order,
            is_numeric($reservedAmount) ? (float) $reservedAmount : null
        );

        return $remaining->getAmount() > 0;
    }

    public static function retry(WC_Order $order, CaptureAllocation $allocation): ?array
    {
        $failedAttempt = self::latest($order);
        if ($failedAttempt === null || $failedAttempt['state'] !== 'failed' || $allocation->getAmount() <= 0) {
            return null;
        }

        $retryClaim = '_buckaroo_klarna_retry_' . $order->get_id() . '_' . $failedAttempt['attempt_number'];
        if (! add_option($retryClaim, 'queued', '', 'no')) {
            return null;
        }

        self::releaseClaim($order, $failedAttempt['allocation_fingerprint']);
        $attempt = self::queue($order, $allocation);
        delete_option($retryClaim);

        return $attempt;
    }

    public static function acquireLock(string $purpose, WC_Order $order, string $key): bool
    {
        global $wpdb;

        $lockName = self::lockName($purpose, $order, $key);

        return (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lockName)) === 1;
    }

    public static function releaseLock(string $purpose, WC_Order $order, string $key): void
    {
        global $wpdb;

        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', self::lockName($purpose, $order, $key)));
    }

    public static function recordAttention(WC_Order $order, array $attempt): void
    {
        if (! in_array($attempt['state'], ['failed', 'unknown'], true)) {
            return;
        }

        $attention = [
            'order_id' => $order->get_id(),
            'attempt_number' => (int) $attempt['attempt_number'],
            'state' => $attempt['state'],
            'amount' => number_format((float) $attempt['amount'], 2, '.', ''),
            'currency' => $attempt['currency'],
            'error' => sanitize_text_field(wp_strip_all_tags((string) $attempt['last_error'])),
            'updated_at' => gmdate('c'),
        ];
        $current = OrderMeta::get($order, self::ACTIVE_ATTENTION_META_KEY);
        if (
            is_array($current) &&
            ($current['attempt_number'] ?? null) === $attention['attempt_number'] &&
            ($current['state'] ?? null) === $attention['state'] &&
            ($current['error'] ?? null) === $attention['error']
        ) {
            return;
        }

        OrderMeta::update($order, self::ACTIVE_ATTENTION_META_KEY, $attention);

        $notifications = get_option(self::NOTIFICATIONS_OPTION, []);
        if (! is_array($notifications)) {
            $notifications = [];
        }
        $notifications[$order->get_id()] = $attention;
        if (count($notifications) > 100) {
            uasort(
                $notifications,
                static function ($first, $second) {
                    return strcmp((string) $first['updated_at'], (string) $second['updated_at']);
                }
            );
            $notifications = array_slice($notifications, -100, null, true);
        }
        update_option(self::NOTIFICATIONS_OPTION, $notifications, false);

        if ($attention['state'] === 'failed') {
            $order->add_order_note(
                sprintf(
                    __('Automatic Klarna capture of %1$s %2$s failed (attempt %3$d): %4$s', 'wc-buckaroo-bpe-gateway'),
                    $attention['amount'],
                    $attention['currency'],
                    $attention['attempt_number'],
                    $attention['error']
                )
            );
        } else {
            $order->add_order_note(
                sprintf(
                    __('Automatic Klarna capture outcome is unknown for %1$s %2$s (attempt %3$d): %4$s', 'wc-buckaroo-bpe-gateway'),
                    $attention['amount'],
                    $attention['currency'],
                    $attention['attempt_number'],
                    $attention['error']
                )
            );
        }
    }

    public static function clearAttention(WC_Order $order): void
    {
        OrderMeta::delete($order, self::ACTIVE_ATTENTION_META_KEY);
        $notifications = get_option(self::NOTIFICATIONS_OPTION, []);
        if (! is_array($notifications)) {
            return;
        }

        unset($notifications[$order->get_id()]);
        update_option(self::NOTIFICATIONS_OPTION, $notifications, false);
    }

    public static function notifications(): array
    {
        $notifications = get_option(self::NOTIFICATIONS_OPTION, []);

        return is_array($notifications) ? $notifications : [];
    }

    private static function claimKey(WC_Order $order, string $fingerprint): string
    {
        return '_buckaroo_klarna_capture_' . $order->get_id() . '_' . substr($fingerprint, 0, 32);
    }

    private static function hasOverlappingAttempt(array $attempts, CaptureAllocation $allocation): bool
    {
        foreach ($attempts as $attempt) {
            if (! in_array($attempt['state'] ?? '', self::BLOCKING_STATES, true)) {
                continue;
            }

            $stored = $attempt['allocation'] ?? [];
            if (! is_array($stored)) {
                continue;
            }

            $existingAllocation = CaptureAllocation::fromArrays(
                is_array($stored['line_item_qtys'] ?? null) ? $stored['line_item_qtys'] : [],
                is_array($stored['line_item_totals'] ?? null) ? $stored['line_item_totals'] : [],
                is_array($stored['line_item_tax_totals'] ?? null) ? $stored['line_item_tax_totals'] : []
            );
            if ($allocation->overlaps($existingAllocation)) {
                return true;
            }
        }

        return false;
    }

    private static function newAttempt(
        WC_Order $order,
        CaptureAllocation $allocation,
        int $attemptNumber,
        string $state,
        string $source
    ): array {
        $now = gmdate('c');

        return [
            'attempt_number' => $attemptNumber,
            'state' => $state,
            'source' => $source,
            'amount' => number_format($allocation->getAmount(), 2, '.', ''),
            'currency' => $order->get_currency(),
            'allocation_fingerprint' => $allocation->fingerprint(),
            'allocation' => [
                'line_item_qtys' => $allocation->getQuantities(),
                'line_item_totals' => $allocation->getTotals(),
                'line_item_tax_totals' => $allocation->getTaxTotals(),
            ],
            'transaction_key' => null,
            'last_error' => '',
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private static function workerClaimKey(WC_Order $order, int $attemptNumber): string
    {
        return '_buckaroo_klarna_capture_worker_' . $order->get_id() . '_' . $attemptNumber;
    }

    private static function lockName(string $purpose, WC_Order $order, string $key): string
    {
        return 'buckaroo_' . $purpose . '_' . substr(hash('sha256', $order->get_id() . ':' . $key), 0, 40);
    }
}
