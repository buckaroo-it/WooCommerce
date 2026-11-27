<?php

namespace Buckaroo\Woocommerce\Gateways\Express;

use InvalidArgumentException;

/**
 * An express payment rejection whose message is written for the shopper. Other
 * exceptions reach the shopper only as a generic message (BTI-1874), because
 * their text can hold internal details.
 */
class ExpressCheckoutException extends InvalidArgumentException
{
    /**
     * Express buttons show messages as text (BTI-1874), so messages written as
     * HTML, such as WooCommerce notices, are sent without markup and entities.
     */
    public static function plainText(string $message): string
    {
        return trim(html_entity_decode(wp_strip_all_tags($message), ENT_QUOTES, 'UTF-8'));
    }
}
