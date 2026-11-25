<?php

namespace Buckaroo\Woocommerce\Services;

/**
 * Error codes passed to the checkout page in the `bck_err` query argument after
 * an unsuccessful payment return.
 *
 * The query argument only carries a code. The shopper-facing text is resolved
 * here, so classic and block checkout show the same translated message.
 */
class CheckoutError
{
    public const QUERY_ARG = 'bck_err';

    public const PAYMENT_FAILED = 'payment_failed';

    public const PAYMENT_CANCELLED = 'payment_cancelled';

    /**
     * Add an error code to a checkout URL. Unknown codes become PAYMENT_FAILED.
     */
    public static function addToUrl(string $url, string $code = self::PAYMENT_FAILED): string
    {
        if (! self::isKnownCode($code)) {
            $code = self::PAYMENT_FAILED;
        }

        return add_query_arg(self::QUERY_ARG, $code, $url);
    }

    /**
     * Translated message for an error code. Any value that is not a known code
     * gets the generic payment error.
     *
     * @param mixed $code
     */
    public static function messageFor($code): string
    {
        if ($code === self::PAYMENT_CANCELLED) {
            return __('Payment cancelled by customer.', 'wc-buckaroo-bpe-gateway');
        }

        return __('Payment unsuccessful. Please try again or choose another payment method.', 'wc-buckaroo-bpe-gateway');
    }

    /**
     * Message for the error code in the current request, or an empty string when
     * the request has no error code.
     */
    public static function messageFromRequest(): string
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Only selects one of the fixed messages to display.
        if (! isset($_GET[self::QUERY_ARG])) {
            return '';
        }

        // Anything other than a known code string shows the generic message.
        $value = is_string($_GET[self::QUERY_ARG]) ? sanitize_text_field(wp_unslash($_GET[self::QUERY_ARG])) : null;
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        if ($value === '') {
            return '';
        }

        return self::messageFor($value);
    }

    /**
     * @param mixed $code
     */
    public static function isKnownCode($code): bool
    {
        return in_array($code, [self::PAYMENT_FAILED, self::PAYMENT_CANCELLED], true);
    }
}
