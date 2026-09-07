<?php

namespace Buckaroo\Woocommerce\Gateways\Klarna;

use Buckaroo\Woocommerce\Order\CaptureAllocation;
use Buckaroo\Woocommerce\Order\OrderMeta;
use Buckaroo\Woocommerce\Services\NamedLock;
use WC_Order;

class KlarnaCaptureAttempt
{
    public const IN_PROGRESS = 'in_progress';

    public const SKIPPED = 'skipped';

    public const STALE_AFTER = 900;

    public const META_KEY = '_buckaroo_klarna_capture_attempts';

    public const NOTIFICATIONS_OPTION = 'buckaroo_klarna_capture_notifications';

    private const BLOCKING_STATES = ['queued', 'in_progress', 'pending', 'unknown'];

    private const CHECKABLE_STATES = ['unknown', 'pending'];

    private const LOCK_WAIT_SECONDS = 5;

    private const NOTIFICATIONS_LOCK = 'buckaroo_klarna_capture_notifications';

    public static function all(WC_Order $order): array
    {
        $order->read_meta_data(true);
        $attempts = OrderMeta::get($order, self::META_KEY);

        return is_array($attempts) ? $attempts : [];
    }

    public static function queue(WC_Order $order, CaptureAllocation $allocation): ?array
    {
        return self::start($order, $allocation, 'queued', 'automatic');
    }

    public static function startManual(WC_Order $order, CaptureAllocation $allocation): ?array
    {
        return self::start($order, $allocation, self::IN_PROGRESS, 'manual', true);
    }

    public static function hasRelatedAttempt(WC_Order $order, CaptureAllocation $allocation): bool
    {
        $attempts = self::all($order);
        $fingerprint = $allocation->fingerprint();
        foreach ($attempts as $attempt) {
            if (($attempt['state'] ?? '') === 'failed') {
                return true;
            }
        }
        if (self::hasFingerprintAttempt($attempts, $fingerprint)) {
            return true;
        }

        return self::hasOverlappingAttempt($attempts, $allocation);
    }

    private static function start(
        WC_Order $order,
        CaptureAllocation $allocation,
        string $state,
        string $source,
        bool $allowFailed = false
    ): ?array {
        $fingerprint = $allocation->fingerprint();
        $claimKey = self::claimKey($order, $fingerprint);

        if (! NamedLock::acquire('attempt_ledger', $order, 'all', self::LOCK_WAIT_SECONDS)) {
            return null;
        }

        try {
            $attempts = self::all($order);
            if (! $allowFailed) {
                foreach ($attempts as $attempt) {
                    if (($attempt['state'] ?? '') === 'failed') {
                        return null;
                    }
                }
            }
            if (self::hasOverlappingAttempt($attempts, $allocation)) {
                return null;
            }
            if (! add_option($claimKey, ['state' => $state], '', 'no')) {
                if (self::hasFingerprintAttempt($attempts, $fingerprint)) {
                    return null;
                }
                delete_option($claimKey);
                if (! add_option($claimKey, ['state' => $state], '', 'no')) {
                    return null;
                }
            }

            $attemptNumber = count($attempts) + 1;
            update_option($claimKey, ['state' => $state, 'attempt_number' => $attemptNumber], false);

            $attempt = self::newAttempt($order, $allocation, $attemptNumber, $state, $source);
            $attempts[] = $attempt;
            if (! OrderMeta::update($order, self::META_KEY, $attempts)) {
                delete_option($claimKey);

                return null;
            }

            return $attempt;
        } finally {
            NamedLock::release('attempt_ledger', $order, 'all');
        }
    }

    public static function claim(WC_Order $order, int $attemptNumber): ?array
    {
        if (! NamedLock::acquire('attempt_ledger', $order, 'all', self::LOCK_WAIT_SECONDS)) {
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
            NamedLock::release('attempt_ledger', $order, 'all');
        }
    }

    public static function recoverStale(WC_Order $order, int $attemptNumber): ?array
    {
        $recovered = self::mutateAttempt(
            $order,
            $attemptNumber,
            [
                'state' => 'unknown',
                'last_error' => __('The capture worker stopped before recording an outcome; reconciliation is required.', 'wc-buckaroo-bpe-gateway'),
            ],
            true,
            self::IN_PROGRESS,
            time() - self::STALE_AFTER
        );
        if ($recovered === null) {
            return null;
        }

        self::releaseWorkerClaim($order, $attemptNumber);
        if (($recovered['state'] ?? '') === 'unknown') {
            self::recordAttention($order, $recovered);
        }

        return $recovered;
    }

    public static function failQueued(WC_Order $order, int $attemptNumber, string $message): ?array
    {
        return self::mutateAttempt(
            $order,
            $attemptNumber,
            [
                'state' => 'failed',
                'last_error' => $message,
            ],
            true,
            'queued'
        );
    }

    public static function updateUnlessSucceeded(WC_Order $order, int $attemptNumber, array $changes): ?array
    {
        $updated = self::mutateAttempt($order, $attemptNumber, $changes, true);
        if ($updated === null) {
            return null;
        }

        $state = $updated['state'] ?? '';
        if (
            in_array($state, ['succeeded', 'skipped'], true) ||
            ($state === 'failed' && ($updated['source'] ?? '') === 'manual')
        ) {
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
        bool $preserveSuccess,
        ?string $expectedState = null,
        ?int $updatedBefore = null
    ): ?array {
        if (! NamedLock::acquire('attempt_ledger', $order, 'all', self::LOCK_WAIT_SECONDS)) {
            return null;
        }

        try {
            $attempts = self::all($order);
            foreach ($attempts as $index => $attempt) {
                if ((int) ($attempt['attempt_number'] ?? 0) !== $attemptNumber) {
                    continue;
                }
                if ($expectedState !== null && ($attempt['state'] ?? '') !== $expectedState) {
                    return null;
                }
                if ($updatedBefore !== null) {
                    $updatedAt = strtotime((string) ($attempt['updated_at'] ?? ''));
                    if ($updatedAt === false || $updatedAt > $updatedBefore) {
                        return null;
                    }
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
            NamedLock::release('attempt_ledger', $order, 'all');
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
        if (
            $attempt === null ||
            $attempt['state'] !== 'failed' ||
            ($attempt['source'] ?? '') !== 'automatic'
        ) {
            return false;
        }

        $reservedAmount = $order->get_meta('_buckaroo_klarna_reserved_amount');
        $remaining = CaptureAllocation::remainingForOrder(
            $order,
            is_numeric($reservedAmount) ? (float) $reservedAmount : null
        );

        return $remaining->getAmount() > 0;
    }

    public static function isCheckable(array $attempt): bool
    {
        return in_array($attempt['state'] ?? '', self::CHECKABLE_STATES, true);
    }

    /**
     * Attempts whose outcome is still unknown or pending, oldest first.
     */
    public static function checkable(WC_Order $order): array
    {
        return array_values(array_filter(self::all($order), [self::class, 'isCheckable']));
    }

    public static function canCheckStatus(WC_Order $order): bool
    {
        return self::checkable($order) !== [];
    }

    /**
     * Mark an attempt failed only while it is still unknown/pending and has no
     * transaction key, so a push that landed in between is never overwritten.
     */
    public static function failUnconfirmed(WC_Order $order, int $attemptNumber, string $message): ?array
    {
        if (! NamedLock::acquire('attempt_ledger', $order, 'all', self::LOCK_WAIT_SECONDS)) {
            return null;
        }

        $failed = null;
        try {
            $attempts = self::all($order);
            foreach ($attempts as $index => $attempt) {
                if ((int) ($attempt['attempt_number'] ?? 0) !== $attemptNumber) {
                    continue;
                }
                if (
                    ! self::isCheckable($attempt) ||
                    trim((string) ($attempt['transaction_key'] ?? '')) !== ''
                ) {
                    return null;
                }

                $attempts[$index] = array_merge($attempt, [
                    'state' => 'failed',
                    'last_error' => $message,
                    'updated_at' => gmdate('c'),
                ]);
                OrderMeta::update($order, self::META_KEY, $attempts);
                $failed = $attempts[$index];
                break;
            }
        } finally {
            NamedLock::release('attempt_ledger', $order, 'all');
        }

        if ($failed === null) {
            return null;
        }

        if (($failed['source'] ?? '') === 'manual') {
            self::releaseClaim($order, (string) $failed['allocation_fingerprint']);
        }
        self::releaseWorkerClaim($order, $attemptNumber);

        return $failed;
    }

    public static function retry(WC_Order $order, CaptureAllocation $allocation): ?array
    {
        $lockKey = 'order';
        if (! NamedLock::acquire('capture_retry', $order, $lockKey, self::LOCK_WAIT_SECONDS)) {
            return null;
        }

        try {
            $failedAttempt = self::latest($order);
            if (
                $failedAttempt === null ||
                $failedAttempt['state'] !== 'failed' ||
                ($failedAttempt['source'] ?? '') !== 'automatic' ||
                $allocation->getAmount() <= 0
            ) {
                return null;
            }

            self::releaseClaim($order, $failedAttempt['allocation_fingerprint']);

            return self::start($order, $allocation, 'queued', 'automatic', true);
        } finally {
            NamedLock::release('capture_retry', $order, $lockKey);
        }
    }

    public static function recordAttention(WC_Order $order, array $attempt): void
    {
        if (! in_array($attempt['state'], ['failed', 'unknown'], true)) {
            return;
        }

        if (! self::acquireNotificationsLock()) {
            return;
        }

        try {
            $attention = [
                'order_id' => $order->get_id(),
                'attempt_number' => (int) $attempt['attempt_number'],
                'state' => $attempt['state'],
                'amount' => number_format((float) $attempt['amount'], 2, '.', ''),
                'currency' => $attempt['currency'],
                'error' => sanitize_text_field(wp_strip_all_tags((string) $attempt['last_error'])),
                'updated_at' => gmdate('c'),
            ];
            $notifications = get_option(self::NOTIFICATIONS_OPTION, []);
            if (! is_array($notifications)) {
                $notifications = [];
            }
            $current = $notifications[$order->get_id()] ?? null;
            if (
                is_array($current) &&
                ($current['attempt_number'] ?? null) === $attention['attempt_number'] &&
                ($current['state'] ?? null) === $attention['state'] &&
                ($current['error'] ?? null) === $attention['error']
            ) {
                return;
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
        } finally {
            self::releaseNotificationsLock();
        }
    }

    public static function clearAttention(WC_Order $order): void
    {
        if (! self::acquireNotificationsLock()) {
            return;
        }

        try {
            $notifications = get_option(self::NOTIFICATIONS_OPTION, []);
            if (! is_array($notifications)) {
                return;
            }

            unset($notifications[$order->get_id()]);
            update_option(self::NOTIFICATIONS_OPTION, $notifications, false);
        } finally {
            self::releaseNotificationsLock();
        }
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

    private static function hasFingerprintAttempt(array $attempts, string $fingerprint): bool
    {
        foreach ($attempts as $attempt) {
            if (($attempt['allocation_fingerprint'] ?? '') === $fingerprint) {
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

    private static function acquireNotificationsLock(): bool
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare('SELECT GET_LOCK(%s, %d)', self::NOTIFICATIONS_LOCK, self::LOCK_WAIT_SECONDS)
        ) === 1;
    }

    private static function releaseNotificationsLock(): void
    {
        global $wpdb;

        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', self::NOTIFICATIONS_LOCK));
    }
}
