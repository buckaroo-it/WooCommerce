<?php

namespace Buckaroo\Woocommerce\Traits;

/**
 * Shared handling of the wallet payment payload (Apple Pay, Google Pay).
 *
 * The payload reaches the processor through three different routes, each with
 * its own quirk:
 *
 *  - Express Checkout button — posted as form-encoded fields, so it arrives as
 *    an array of WordPress slash-escaped strings.
 *  - Classic checkout method — one JSON string in a hidden field, slash-escaped
 *    by WordPress like any other $_POST value.
 *  - Blocks checkout method — one JSON string sent through the Store API, which
 *    lowercases every key (sanitize_key() in CheckoutTrait) before copying the
 *    data into $_POST, and does NOT slash-escape the value.
 *
 * Both quirks silently empty the token if ignored: the key is looked up
 * case-insensitively, and the JSON is only unslashed when it does not already
 * parse. A Google Pay token carries a JSON-encoded `signedMessage`, so
 * unslashing an unslashed payload strips escaping the token needs and the
 * gateway rejects the transaction.
 */
trait HandlesWalletPaymentData
{
    /**
     * Read and decode the posted wallet payload.
     */
    protected function getWalletPaymentData(): array
    {
        foreach (['paymentData', 'paymentdata'] as $key) {
            $raw = $this->request->input($key);

            if ($raw !== null && $raw !== '') {
                return $this->normalizeWalletPaymentData($raw);
            }
        }

        return [];
    }

    /**
     * @param  mixed  $data
     */
    protected function normalizeWalletPaymentData($data): array
    {
        if (is_array($data)) {
            return wp_unslash($data);
        }

        if (! is_string($data)) {
            return [];
        }

        // Decode the raw string first so escaped characters inside the wallet
        // token survive the Blocks route; only a failure to parse means the
        // slashes were added by WordPress and have to come off.
        $decoded = json_decode($data, true);

        if (! is_array($decoded)) {
            $decoded = json_decode(wp_unslash($data), true);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Cardholder name for the transaction request.
     *
     * The wallet sheet only returns a name when the merchant asked for it, which
     * the standard checkout method deliberately does not: there the shopper
     * already filled in the checkout form, so the order is the source of truth.
     */
    protected function resolveWalletCustomerName(array $data): string
    {
        foreach (['billingContact', 'shippingContact'] as $contactKey) {
            $contact = $data[$contactKey] ?? [];

            if (! is_array($contact)) {
                continue;
            }

            $name = trim(
                sanitize_text_field($contact['givenName'] ?? '') . ' ' .
                sanitize_text_field($contact['familyName'] ?? '')
            );

            if ($name !== '') {
                return $name;
            }
        }

        return trim(
            $this->getAddress('billing', 'first_name') . ' ' . $this->getAddress('billing', 'last_name')
        );
    }

    /**
     * Base64 encode the wallet token, which is an array for Apple Pay and an
     * already-serialised JSON string for Google Pay.
     *
     * @param  mixed  $token
     */
    protected function encodeWalletToken($token): string
    {
        return base64_encode(is_array($token) ? json_encode($token) : (string) $token);
    }
}
