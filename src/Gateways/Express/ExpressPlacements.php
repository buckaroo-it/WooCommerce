<?php

namespace Buckaroo\Woocommerce\Gateways\Express;

/**
 * Where an express button is shown, resolved from stored settings.
 *
 * Placements moved from three button_{location} keys to a single list under
 * express_show_on. The legacy keys stay readable so an install whose upgrade
 * routine never ran keeps rendering the button in the same places: migrations
 * only fire on upgrader_process_complete, never on an FTP, rsync or container
 * deploy.
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
        // array_key_exists, never empty(): WooCommerce stores an emptied
        // multiselect as '' (validate_multiselect_field returns '' when
        // nothing was posted). That means "nothing selected", so it must not
        // fall through to the legacy keys, whose absent value reads as shown.
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

        // Dual-write for the two-release window. express_show_on is what reads
        // resolve from, but any consumer still on the legacy keys - including
        // third-party code - keeps seeing the current placements.
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
