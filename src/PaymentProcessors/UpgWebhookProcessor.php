<?php

namespace Buckaroo\Woocommerce\PaymentProcessors;

use Buckaroo\Woocommerce\Order\OrderMeta;
use Buckaroo\Woocommerce\ResponseParser\UpgResponseParser;
use Buckaroo\Woocommerce\Services\Logger;
use Buckaroo\Woocommerce\Services\UpgSettings;
use BuckarooDeps\Buckaroo\Adapter\Notification;
use BuckarooDeps\Buckaroo\Adapter\Status;
use BuckarooDeps\Buckaroo\Adapter\Upg\UpgWebhook;
use Throwable;
use WC_Order;

/**
 * The UPG webhook endpoint. UPG sends webhooks to one URL per merchant, signed differently from a BPE push,
 * so it has its own endpoint; the order logic is the BPE push's.
 */
class UpgWebhookProcessor
{
    /** Delivery ids already handled, per order. */
    private const DELIVERIES_META = '_buckaroo_upg_deliveries';

    private const DELIVERIES_KEPT = 50;

    private PushProcessor $push;

    public function __construct()
    {
        $this->push = new PushProcessor();
    }

    /** The webhook request as it arrived: its headers and its raw body. */
    public function handleRequest(): int
    {
        return $this->handle($this->push->getAllHeaders(), (string) file_get_contents('php://input'));
    }

    /**
     * @param  array  $headers  the request headers
     * @param  string  $body  the raw request body, byte for byte: the signature covers it
     * @return int the HTTP status to answer
     */
    public function handle(array $headers, string $body): int
    {
        try {
            // Both modes' secrets: the request cannot say its mode in a signed way.
            $notification = UpgWebhook::handle($headers, $body, UpgSettings::webhookSecrets(), UpgSettings::webhookUrl());
        } catch (Throwable $e) {
            Logger::log(__METHOD__, 'UPG webhook rejected: ' . $e->getMessage());

            return 401;
        }

        if ($notification === null) {
            return 200;
        }

        // Not final yet. Holding the order here would mail the consumer "on hold" for a payment still in progress.
        if ($notification->result->status() === Status::Pending) {
            return 200;
        }

        $order = UpgResponseParser::orderToUpdate($notification->result);
        if ($order === null) {
            Logger::log(__METHOD__, 'UPG webhook for an unknown order or an attempt that may not change it; nothing changed.');

            return 200;
        }

        $deliveries = $this->deliveries($order);
        if (in_array($notification->idempotencyKey, $deliveries['keys'], true)) {
            return 200;
        }
        if (in_array($notification->nonce, $deliveries['nonces'], true)) {
            Logger::log(__METHOD__, 'UPG webhook replayed (nonce seen before); rejected.');

            return 401;
        }

        $parser = new UpgResponseParser(
            $notification->result,
            str_replace('buckaroo_', '', $order->get_payment_method()),
            (bool) OrderMeta::get($order, '_buckaroo_order_in_test_mode')
        );
        $this->push->processVerified($order->get_id(), $order, $parser);
        $this->remember($order, $notification, $deliveries);

        return 200;
    }

    private function deliveries(WC_Order $order): array
    {
        $deliveries = OrderMeta::get($order, self::DELIVERIES_META);

        return [
            'keys' => is_array($deliveries['keys'] ?? null) ? $deliveries['keys'] : [],
            'nonces' => is_array($deliveries['nonces'] ?? null) ? $deliveries['nonces'] : [],
        ];
    }

    private function remember(WC_Order $order, Notification $notification, array $deliveries): void
    {
        foreach (['keys' => $notification->idempotencyKey, 'nonces' => $notification->nonce] as $list => $id) {
            if ($id !== null) {
                $deliveries[$list] = array_slice(array_merge($deliveries[$list], [$id]), -self::DELIVERIES_KEPT);
            }
        }
        OrderMeta::update($order, self::DELIVERIES_META, $deliveries);
    }
}
