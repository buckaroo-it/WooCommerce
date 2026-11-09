<?php

namespace Buckaroo\Woocommerce\Gateways\Express;

/**
 * Where an express button is shown, resolved from stored settings: the
 * express_show_on list when present, otherwise the button_{location} keys.
 */
final class ExpressPlacements
{
    public const SHOW_ON_KEY = 'express_show_on';

    /**
     * @var array<string>
     */
    public const LOCATIONS = ['product', 'cart', 'checkout'];

    /**
     * @param  array<string, mixed>  $stored
     * @return array<string>
     */
    public static function fromSettings(array $stored): array
    {
        // WooCommerce stores an emptied multiselect as '', which means "nothing
        // selected" and must not fall through to the legacy keys.
        if (array_key_exists(self::SHOW_ON_KEY, $stored)) {
            $selected = $stored[self::SHOW_ON_KEY];

            return is_array($selected) ? self::onlyKnown($selected) : [];
        }

        $selected = [];

        foreach (self::LOCATIONS as $location) {
            if (($stored['button_' . $location] ?? 'TRUE') === 'TRUE') {
                $selected[] = $location;
            }
        }

        return $selected;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string>  $selected
     * @return array<string, mixed>
     */
    public static function toSettings(array $settings, array $selected): array
    {
        $selected = self::onlyKnown($selected);
        $settings[self::SHOW_ON_KEY] = $selected;

        // Keep the legacy keys in sync for code that still reads them.
        foreach (self::LOCATIONS as $location) {
            $settings['button_' . $location] = in_array($location, $selected, true) ? 'TRUE' : 'FALSE';
        }

        return $settings;
    }

    /**
     * @return array<string>
     */
    public static function forGateway(string $gatewayId): array
    {
        $stored = get_option('woocommerce_' . $gatewayId . '_settings', []);

        return self::fromSettings(is_array($stored) ? $stored : []);
    }

    public static function enabledFor(string $gatewayId, string $location): bool
    {
        return in_array($location, self::forGateway($gatewayId), true);
    }

    /**
     * @param  array<mixed>  $locations
     * @return array<string>
     */
    private static function onlyKnown(array $locations): array
    {
        return array_values(array_intersect(self::LOCATIONS, $locations));
    }
}
