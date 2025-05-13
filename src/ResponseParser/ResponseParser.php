<?php

namespace Buckaroo\Woocommerce\ResponseParser;

use Buckaroo\Woocommerce\Constraints\BuckarooTransactionStatus;
use BuckarooDeps\Buckaroo\Resources\Constants\ResponseStatus;

abstract class ResponseParser implements IResponseParser
{
    protected array $items;

    protected array $unformattedItems;

    public function __construct(array $items = [])
    {
        $this->unformattedItems = $items;
        $this->items = $this->normalizeItems($items);
    }

    protected function normalizeItems(array $array): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $lowerKey = is_string($key) ? strtolower($key) : $key;

            if (is_array($value)) {
                $result[$lowerKey] = $this->normalizeItems($value);
            } else {
                $result[$lowerKey] = $value;
            }
        }

        return $result;
    }

    public static function make($items = [])
    {
        $filtered = array_filter(
            $items,
            function ($item, $key) {
                return str_starts_with(strtolower($key), 'brq_');
            },
            ARRAY_FILTER_USE_BOTH
        );

        if (empty($filtered)) {
            return new JsonParser($items);
        } else {
            return new FormDataParser($items);
        }
    }

    public function get($key = null, $default = null, $formatted = true)
    {
        return $this->getDeep($formatted ? $this->items : $this->unformattedItems, $key ? strtolower($key) : null, $default);
    }

    protected function getDeep($array, $key, $default = null)
    {
        if (! is_array($array)) {
            return $default;
        }

        if (is_null($key) || empty($key)) {
            return $array;
        }

        if (isset($array[$key])) {
            return $array[$key];
        }

        $keys = explode('.', $key);

        foreach ($keys as $segment) {
            if (is_array($array) && array_key_exists($segment, $array)) {
                $array = $array[$segment];
            } else {
                return $default;
            }
        }

        return $array;
    }

    public function set($key, $value)
    {
        $this->setDeep($this->items, strtolower($key), $value);
    }

    protected function setDeep(&$array, $key, $value): void
    {
        if (! is_array($array)) {
            $array = [];
        }

        $keys = explode('.', $key);

        while (count($keys) > 1) {
            $segment = array_shift($keys);

            if (! isset($array[$segment]) || ! is_array($array[$segment])) {
                $array[$segment] = [];
            }

            $array = &$array[$segment];
        }

        $array[array_shift($keys)] = $value;
    }

    public function firstWhere($array, $key, $value)
    {
        if (is_array($array)) {
            foreach ($array as $item) {
                if (isset($item[$key]) && $item[$key] == $value) {
                    return $item;
                }
            }
        }

        return null;
    }

    public function isOnHold(): bool
    {
        return BuckarooTransactionStatus::fromTransactionStatus($this->getStatusCode()) == BuckarooTransactionStatus::STATUS_ON_HOLD;
    }

    public function isSuccess(): bool
    {
        $buckarooTransactionStatus = BuckarooTransactionStatus::fromTransactionStatus($this->getStatusCode());

        return ! ($buckarooTransactionStatus === BuckarooTransactionStatus::STATUS_ON_HOLD && $this->getPaymentMethod() === 'paypal') &&
            ($buckarooTransactionStatus === BuckarooTransactionStatus::STATUS_ON_HOLD || $buckarooTransactionStatus === BuckarooTransactionStatus::STATUS_COMPLETED);
    }

    public function isFailed(): bool
    {
        $buckarooTransactionStatus = BuckarooTransactionStatus::fromTransactionStatus($this->getStatusCode());

        return $buckarooTransactionStatus === BuckarooTransactionStatus::STATUS_FAILED;
    }

    public function getStatusMessage(): bool
    {
        return BuckarooTransactionStatus::getMessageFromCode($this->getStatusCode());
    }

    public function isPendingProcessing(): bool
    {
        return BuckarooTransactionStatus::fromTransactionStatus($this->getStatusCode()) == BuckarooTransactionStatus::STATUS_PENDING ||
            in_array($this->getSubStatusCode(), ['P190', 'P191']);
    }

    public function isPendingApproval(): bool
    {
        return $this->getStatusCode() == ResponseStatus::BUCKAROO_STATUSCODE_PENDING_APPROVAL;
    }

    public function isCanceled(): bool
    {
        return $this->getStatusCode() == ResponseStatus::BUCKAROO_STATUSCODE_CANCELLED_BY_USER
            || $this->getStatusCode() == ResponseStatus::BUCKAROO_STATUSCODE_CANCELLED_BY_MERCHANT;
    }

    public function isAwaitingConsumer(): bool
    {
        return $this->getStatusCode() == ResponseStatus::BUCKAROO_STATUSCODE_WAITING_ON_CONSUMER;
    }

    protected function formatAmount($amount): ?float
    {
        return is_numeric($amount) ? (float) $amount : null;
    }
}
