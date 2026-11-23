<?php

namespace Buckaroo\Woocommerce\PaymentProcessors\Actions;

use Buckaroo\Woocommerce\Constraints\BuckarooTransactionStatus;
use Buckaroo\Woocommerce\Gateways\AbstractRefundProcessor;
use Buckaroo\Woocommerce\Install\Migration\Versions\MigrateOrderMetaToHpos;
use Buckaroo\Woocommerce\Order\OrderMeta;
use Buckaroo\Woocommerce\Order\RefundLedger;
use Buckaroo\Woocommerce\PaymentProcessors\PushProcessor;
use Buckaroo\Woocommerce\ResponseParser\ResponseParser;
use Buckaroo\Woocommerce\Services\BuckarooClient;
use Buckaroo\Woocommerce\Services\Helper;
use Buckaroo\Woocommerce\Services\Logger;
use Buckaroo\Woocommerce\Services\NamedLock;
use BuckarooDeps\Buckaroo\Resources\Constants\ResponseStatus;
use BuckarooDeps\Buckaroo\Transaction\Response\TransactionResponse;
use RuntimeException;
use Throwable;
use WC_Order;
use WC_Order_Refund;
use WP_Error;

class RefundAction
{
    /** Original transaction for refunds started inside sendAgainst(). */
    private static ?string $requestedOriginalTransactionKey = null;

    /** @var array<int, WC_Order_Refund> Refund WooCommerce is creating in this request, by order id */
    private static array $createdRefunds = [];

    protected ?string $originalTransactionKey;

    protected AbstractRefundProcessor $paymentProcessor;

    protected BuckarooClient $buckarooClient;

    private WC_Order $order;

    public function __construct(
        AbstractRefundProcessor $paymentProcessor,
        $orderId,
        ?string $originalTransactionKey,
        ?BuckarooClient $buckarooClient = null
    ) {
        $this->paymentProcessor = $paymentProcessor;
        $this->order = new WC_Order($orderId);
        $this->buckarooClient = $buckarooClient ?? new BuckarooClient($this->paymentProcessor->gateway->getMode());
        $this->originalTransactionKey = $originalTransactionKey ?? self::$requestedOriginalTransactionKey;
    }

    /**
     * Run $callback (e.g. wc_create_refund with refund_payment) so that the
     * gateway refund it starts is sent against this original transaction.
     *
     * @return mixed The callback result
     */
    public static function sendAgainst(string $originalTransactionKey, callable $callback)
    {
        $previous = self::$requestedOriginalTransactionKey;
        self::$requestedOriginalTransactionKey = $originalTransactionKey;

        try {
            return $callback();
        } finally {
            self::$requestedOriginalTransactionKey = $previous;
        }
    }

    /**
     * woocommerce_create_refund: WooCommerce saves this refund and then calls
     * the gateway, so process() can link the exact refund it belongs to.
     *
     * @param  mixed  $refund
     */
    public static function rememberCreatedRefund($refund): void
    {
        if ($refund instanceof WC_Order_Refund) {
            self::$createdRefunds[(int) $refund->get_parent_id()] = $refund;
        }
    }

    public static function initiateExternalServiceRefund($order_id, ResponseParser $responseParser)
    {
        Logger::log('PUSH', 'Refund payment PUSH received ' . $responseParser->get('coreStatus'));
        $order = wc_get_order($order_id);
        if (! $order instanceof WC_Order) {
            exit();
        }

        self::handleRefundPush($order, $responseParser);
        exit();
    }

    /**
     * Record a refund reported by a Buckaroo push. A refund is recorded once,
     * only when Buckaroo reports it as successful, only against a payment or
     * capture of this order and only within the amount still refundable. A
     * refund this site already recorded, or is still sending, is not
     * recorded again.
     */
    public static function handleRefundPush(WC_Order $order, ResponseParser $responseParser): void
    {
        if (! NamedLock::acquire(PushProcessor::SETTLEMENT_LOCK_PURPOSE, $order, PushProcessor::SETTLEMENT_LOCK_KEY, PushProcessor::SETTLEMENT_LOCK_WAIT_SECONDS)) {
            throw new RuntimeException(
                esc_html__('Buckaroo push could not be processed because the order is locked.', 'wc-buckaroo-bpe-gateway')
            );
        }

        try {
            $order->read_meta_data(true);
            $allowedPush = OrderMeta::get($order, '_pushallowed');
            Logger::log(__METHOD__ . '|10|', $allowedPush);
            if ($allowedPush != 'ok') {
                return;
            }

            $refundKey = (string) $responseParser->getTransactionKey();
            $parentKey = (string) $responseParser->getRefundParentKey();
            if (! Helper::isValidTransactionKey($refundKey) || ! Helper::isValidTransactionKey($parentKey)) {
                Logger::log(__METHOD__, 'Refund push ignored: missing transaction key.');

                return;
            }

            $entryId = RefundLedger::findByRefundKey($order, $refundKey);
            $status = BuckarooTransactionStatus::fromTransactionStatus($responseParser->getStatusCode());

            if ($status !== BuckarooTransactionStatus::STATUS_COMPLETED) {
                self::handleUnfinishedRefundPush($order, $entryId, $status, $refundKey);

                return;
            }

            if ($entryId !== null) {
                $entry = RefundLedger::get($order, $entryId);
                if (($entry['state'] ?? null) === RefundLedger::STATE_PENDING) {
                    RefundLedger::update($order, $entryId, ['state' => RefundLedger::STATE_COMPLETED]);
                }

                return;
            }

            if (OrderMeta::get($order, '_refundbuckaroo' . $refundKey)) {
                return;
            }

            if (! RefundLedger::isOrderTransaction($order, $parentKey)) {
                Logger::log(__METHOD__, 'Refund push ' . $refundKey . ' ignored: original transaction ' . $parentKey . ' is not part of the order.');
                $order->add_order_note(
                    sprintf(
                        __('Buckaroo refund %1$s was not recorded, because its original transaction %2$s is not a payment or capture of this order. Check the refund in Buckaroo Plaza.', 'wc-buckaroo-bpe-gateway'),
                        esc_html($refundKey),
                        esc_html($parentKey)
                    )
                );

                return;
            }

            $cents = RefundLedger::toCents($responseParser->getAmountCredit());
            if ($cents === null || $cents <= 0) {
                Logger::log(__METHOD__, 'Refund push ' . $refundKey . ' ignored: invalid amount.');

                return;
            }

            $reservationId = RefundLedger::findReservation($order, $parentKey, $cents);
            if ($reservationId !== null) {
                self::completeReservation($order, $reservationId, $refundKey, $cents);

                return;
            }

            if (! self::isWithinRefundable($order, $parentKey, $cents)) {
                Logger::log(__METHOD__, 'Refund push ' . $refundKey . ' ignored: amount exceeds the refundable amount.');
                $order->add_order_note(
                    sprintf(
                        __('Buckaroo refund %1$s of %2$s was not recorded, because it is more than the amount that can still be refunded. Check the refund in Buckaroo Plaza.', 'wc-buckaroo-bpe-gateway'),
                        esc_html($refundKey),
                        wc_price(RefundLedger::formatCents($cents), ['currency' => $order->get_currency()])
                    )
                );

                return;
            }

            OrderMeta::add($order, '_refundbuckaroo' . $refundKey, 'ok', true);
            $entryId = RefundLedger::addCompleted($order, $parentKey, $cents, $refundKey);
            self::createWooCommerceRefund($order, $cents, $entryId, $refundKey);
        } finally {
            NamedLock::release(PushProcessor::SETTLEMENT_LOCK_PURPOSE, $order, PushProcessor::SETTLEMENT_LOCK_KEY);
        }
    }

    private static function handleUnfinishedRefundPush(WC_Order $order, ?string $entryId, string $status, string $refundKey): void
    {
        $entry = $entryId !== null ? RefundLedger::get($order, $entryId) : null;
        if (
            $entry !== null &&
            ($entry['state'] ?? null) === RefundLedger::STATE_PENDING &&
            in_array($status, [BuckarooTransactionStatus::STATUS_FAILED, BuckarooTransactionStatus::STATUS_CANCELLED], true)
        ) {
            RefundLedger::update($order, $entryId, ['state' => RefundLedger::STATE_FAILED]);
            $order->add_order_note(
                sprintf(
                    __('Buckaroo reported refund %s as not completed. The WooCommerce refund was kept; review it and the refund in Buckaroo Plaza.', 'wc-buckaroo-bpe-gateway'),
                    esc_html($refundKey)
                )
            );

            return;
        }

        Logger::log(__METHOD__, 'Refund push ' . $refundKey . ' with status ' . $status . ' is not final; no refund recorded.');
    }

    /**
     * A push for a refund this site sent without receiving a usable
     * response. Its WooCommerce refund is created only when it no longer
     * exists.
     */
    private static function completeReservation(WC_Order $order, string $reservationId, string $refundKey, int $cents): void
    {
        OrderMeta::add($order, '_refundbuckaroo' . $refundKey, 'ok', true);
        RefundLedger::update($order, $reservationId, [
            'state' => RefundLedger::STATE_COMPLETED,
            'refund_key' => $refundKey,
        ]);

        $reservation = RefundLedger::get($order, $reservationId);
        $wcRefundId = (int) ($reservation['wc_refund_id'] ?? 0);
        if ($wcRefundId > 0 && wc_get_order($wcRefundId) instanceof WC_Order_Refund) {
            return;
        }

        self::createWooCommerceRefund($order, $cents, $reservationId, $refundKey, false);
    }

    private static function isWithinRefundable(WC_Order $order, string $parentKey, int $cents): bool
    {
        $orderRemaining = RefundLedger::toCents($order->get_remaining_refund_amount());
        if ($orderRemaining === null || $cents > $orderRemaining) {
            return false;
        }

        $captureRemaining = RefundLedger::remainingCaptureCents($order, $parentKey);

        return $captureRemaining === null || $cents <= $captureRemaining;
    }

    /**
     * @param  bool  $removeOnFailure  Remove the ledger entry and the refund key
     *                                 when WooCommerce does not create the refund
     */
    private static function createWooCommerceRefund(
        WC_Order $order,
        int $cents,
        string $entryId,
        string $refundKey,
        bool $removeOnFailure = true
    ): void {
        $refund = wc_create_refund(
            [
                'amount' => RefundLedger::formatCents($cents),
                'reason' => __('Refunded', 'wc-buckaroo-bpe-gateway'),
                'order_id' => $order->get_id(),
                'line_items' => [],
            ]
        );

        if ($refund instanceof WC_Order_Refund) {
            $order->read_meta_data(true);
            RefundLedger::update($order, $entryId, ['wc_refund_id' => $refund->get_id()]);

            return;
        }

        $message = $refund instanceof WP_Error ? $refund->get_error_message() : 'unknown error';
        Logger::log(__METHOD__, 'Could not create the WooCommerce refund for ' . $refundKey . ': ' . $message);

        if ($removeOnFailure) {
            $order->read_meta_data(true);
            RefundLedger::remove($order, $entryId);
            OrderMeta::delete($order, '_refundbuckaroo' . $refundKey);
        }

        throw new RuntimeException(
            esc_html__('The WooCommerce refund for this Buckaroo push could not be created.', 'wc-buckaroo-bpe-gateway')
        );
    }

    /**
     * Send the refund to Buckaroo. Under the settlement lock the amount is
     * checked against what is still refundable and reserved before the
     * request, so a concurrent refund or push cannot use the same amount.
     *
     * @return true|WP_Error
     */
    public function process()
    {
        $cents = RefundLedger::toCents($this->paymentProcessor->getAmount());
        if ($cents === null || $cents <= 0) {
            return new WP_Error('error_refund', __('A valid refund amount is required.', 'wc-buckaroo-bpe-gateway'));
        }

        if (! NamedLock::acquire(PushProcessor::SETTLEMENT_LOCK_PURPOSE, $this->order, PushProcessor::SETTLEMENT_LOCK_KEY, PushProcessor::SETTLEMENT_LOCK_WAIT_SECONDS)) {
            return new WP_Error(
                'error_refund',
                __('Another refund or payment update for this order is being processed. Please try again in a moment.', 'wc-buckaroo-bpe-gateway')
            );
        }

        try {
            MigrateOrderMetaToHpos::ensureOrderMigrated($this->order);
            $this->order->read_meta_data(true);

            $parentKey = RefundLedger::selectParent($this->order, $cents, $this->originalTransactionKey);
            if ($parentKey === null) {
                return new WP_Error('error_refund', $this->notRefundableMessage());
            }

            $reservationId = RefundLedger::reserve($this->order, $parentKey, $cents, $this->findWooCommerceRefundId($cents));

            try {
                $transactionResponse = $this->buckarooClient->process(
                    $this->paymentProcessor,
                    $parentKey !== '' ? ['originalTransactionKey' => $parentKey] : []
                );
            } catch (Throwable $exception) {
                // The request may have reached Buckaroo: keep the reservation,
                // a later push records the refund.
                Logger::log(__METHOD__, $exception->getMessage());
                $this->order->add_order_note(
                    sprintf(
                        __('Refund of %s was sent to Buckaroo, but no result was received. It is recorded when Buckaroo reports it. Check Buckaroo Plaza before refunding this amount again.', 'wc-buckaroo-bpe-gateway'),
                        wc_price(RefundLedger::formatCents($cents), ['currency' => $this->order->get_currency()])
                    )
                );

                return new WP_Error(
                    'error_refund',
                    __('No result was received from Buckaroo for this refund. It is recorded when Buckaroo reports it. Check Buckaroo Plaza before trying again.', 'wc-buckaroo-bpe-gateway')
                );
            }

            return $this->finalize($transactionResponse, $reservationId);
        } finally {
            NamedLock::release(PushProcessor::SETTLEMENT_LOCK_PURPOSE, $this->order, PushProcessor::SETTLEMENT_LOCK_KEY);
        }
    }

    /**
     * @param  string|null  $reservationId  Ledger entry reserved for this refund
     * @return true|WP_Error
     */
    public function finalize(TransactionResponse $transactionResponse, ?string $reservationId = null)
    {
        if ($transactionResponse->isSuccess()) {
            $this->order->add_order_note(
                sprintf(
                    __('Refunded %1$s - Refund transaction ID: %2$s', 'wc-buckaroo-bpe-gateway'),
                    wc_price($transactionResponse->get('AmountCredit')),
                    $transactionResponse->getTransactionKey()
                )
            );
            $this->recordAcceptedRefund($transactionResponse, $reservationId, RefundLedger::STATE_COMPLETED);

            return true;
        }

        if ($this->isPendingRefund($transactionResponse)) {
            $this->order->add_order_note(
                sprintf(
                    __('Refund of %1$s is pending processing - Transaction ID: %2$s', 'wc-buckaroo-bpe-gateway'),
                    wc_price($transactionResponse->get('AmountCredit')),
                    $transactionResponse->getTransactionKey()
                )
            );
            $this->recordAcceptedRefund($transactionResponse, $reservationId, RefundLedger::STATE_PENDING);

            return true;
        }

        if ($reservationId !== null) {
            RefundLedger::remove($this->order, $reservationId);
        }

        $this->order->add_order_note(
            sprintf(
                __(
                    'Refund failed for transaction ID: %s ' . "\n" . $transactionResponse->getSomeError(),
                    'wc-buckaroo-bpe-gateway'
                ),
                $this->order->get_transaction_id()
            )
        );

        return new WP_Error('error_refund', __('Refund failed: ') . $transactionResponse->getSomeError());
    }

    private function recordAcceptedRefund(TransactionResponse $transactionResponse, ?string $reservationId, string $state): void
    {
        $refundKey = (string) $transactionResponse->getTransactionKey();

        OrderMeta::add($this->order, '_refundbuckaroo' . $refundKey, 'ok', true);

        if ($reservationId !== null) {
            RefundLedger::update($this->order, $reservationId, [
                'state' => $state,
                'refund_key' => $refundKey !== '' ? $refundKey : null,
            ]);
        }
    }

    /**
     * The WooCommerce refund created for this request: WooCommerce creates
     * it before calling the gateway. Without one, the newest unpaid refund of
     * this amount that no ledger entry uses yet.
     */
    private function findWooCommerceRefundId(int $cents): ?int
    {
        $created = self::$createdRefunds[$this->order->get_id()] ?? null;
        if (
            $created instanceof WC_Order_Refund &&
            $created->get_id() > 0 &&
            ! $created->get_refunded_payment() &&
            RefundLedger::toCents(abs((float) $created->get_amount())) === $cents
        ) {
            return $created->get_id();
        }

        $linked = [];
        foreach (RefundLedger::entries($this->order) as $entry) {
            if (! empty($entry['wc_refund_id'])) {
                $linked[] = (int) $entry['wc_refund_id'];
            }
        }

        $match = null;
        foreach ($this->order->get_refunds() as $refund) {
            if (
                ! $refund instanceof WC_Order_Refund ||
                $refund->get_refunded_payment() ||
                in_array($refund->get_id(), $linked, true) ||
                RefundLedger::toCents(abs((float) $refund->get_amount())) !== $cents
            ) {
                continue;
            }

            if ($match === null || $refund->get_id() > $match) {
                $match = $refund->get_id();
            }
        }

        return $match;
    }

    private function notRefundableMessage(): string
    {
        if (! empty(RefundLedger::captures($this->order))) {
            return __('The refund amount is more than what can still be refunded on a single capture of this order. Refund at most the remaining amount of one capture at a time.', 'wc-buckaroo-bpe-gateway');
        }

        return __('The refund amount is more than what can still be refunded for this order.', 'wc-buckaroo-bpe-gateway');
    }

    private function isPendingRefund(TransactionResponse $transactionResponse): bool
    {
        $statusCode = $transactionResponse->getStatusCode();

        return $transactionResponse->isPendingProcessing()
            || $statusCode == ResponseStatus::BUCKAROO_STATUSCODE_PAYMENT_ON_HOLD;
    }
}
