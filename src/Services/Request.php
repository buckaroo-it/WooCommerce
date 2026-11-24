<?php

namespace Buckaroo\Woocommerce\Services;

class Request
{
    public static function make()
    {
        return new static();
    }

    public function all(): array
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing -- Shared accessor; callers run behind the checkout nonce or verify their own.
        return array_merge($_GET, $_POST);
    }

    public function input($key = null, $default = null)
    {
        $input = $this->all();

        if ($key === null) {
            return $input;
        }

        return map_deep($input[$key] ?? $default, 'sanitize_text_field');
    }

    public function query($key = null, $default = null)
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Shared accessor; callers verify their own nonce.
        $query = map_deep(wp_unslash($_GET), 'sanitize_text_field');

        if ($key === null) {
            return $query;
        }

        return $query[$key] ?? $default;
    }

    public function post($key = null, $default = null)
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Shared accessor; callers verify their own nonce.
        $post = map_deep(wp_unslash($_POST), 'sanitize_text_field');

        if ($key === null) {
            return $post;
        }

        return $post[$key] ?? $default;
    }

    public function only($keys)
    {
        $input = $this->input();

        $keys = is_array($keys) ? $keys : func_get_args();

        return array_intersect_key($input, array_flip($keys));
    }

    public function except($keys)
    {
        $input = $this->input();

        $keys = is_array($keys) ? $keys : func_get_args();

        return array_diff_key($input, array_flip($keys));
    }

    public function has($key): bool
    {
        $keys = is_array($key) ? $key : func_get_args();

        foreach ($keys as $value) {
            if ($this->isEmptyString($this->input($value))) {
                return false;
            }
        }

        return true;
    }

    public function exists($key): bool
    {
        $input = $this->input();

        $keys = is_array($key) ? $key : func_get_args();

        foreach ($keys as $value) {
            if (! array_key_exists($value, $input)) {
                return false;
            }
        }

        return true;
    }

    public function method()
    {
        if (! isset($_SERVER['REQUEST_METHOD']) || ! is_string($_SERVER['REQUEST_METHOD'])) {
            return 'GET';
        }

        return sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD']));
    }

    public function getContent()
    {
        return file_get_contents('php://input');
    }

    protected function sanitizeData($data)
    {
        return map_deep($data, 'sanitize_text_field');
    }

    protected function isEmptyString($value)
    {
        return ! isset($value) || $value === '' || $value === null;
    }
}
