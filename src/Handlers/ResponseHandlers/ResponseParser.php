<?php

namespace Buckaroo\Woocommerce\Handlers\ResponseHandlers;

abstract class ResponseParser implements ResponseParserInterface
{
    protected $request;

    public function __construct(array $items = [])
    {
        $this->request = $_REQUEST;
        $this->items = array_change_key_case($items, CASE_LOWER);
    }

    public static function make($items = [])
    {
        $filteredItems = array_filter($items, function ($item, $key) {
            return strpos(strtolower($key), 'brq_') === 0;
        }, ARRAY_FILTER_USE_BOTH);

        return empty($filteredItems) ? new JsonParser($items) : new FormDataParser($items);
    }

    protected function formatAmount($amount): ?float
    {
        return is_numeric($amount) ? (float)$amount : null;
    }

    protected function getDeep($key = null, $default = null)
    {
        return self::dataGet($this->items, $key, $default);
    }

    // Implement the data_get helper method similar to Laravel's functionality.
    public static function dataGet($array, $key, $default = null)
    {
        if (is_null($key)) {
            return $array;
        }
        foreach (explode('.', $key) as $segment) {
            if (is_array($array) && array_key_exists($segment, $array)) {
                $array = $array[$segment];
            } else {
                return $default;
            }
        }
        return $array;
    }
}
