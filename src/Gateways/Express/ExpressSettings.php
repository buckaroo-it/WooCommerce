<?php

namespace Buckaroo\Woocommerce\Gateways\Express;

/**
 * Shared settings structure for the express payment methods.
 *
 * A gateway declares what it supports in expressSettingsSpec() and this trait
 * assembles the sections in a fixed order. Sections without fields are not
 * rendered and unsupported fields are absent rather than disabled.
 *
 * Storage keys are unchanged: the placement widget is a single multiselect in
 * the UI, expanded back into the legacy button_{location} keys on save.
 */
trait ExpressSettings
{
    /**
     * Locations an express button can be shown on.
     *
     * @var array<string>
     */
    protected static $expressLocations = ['product', 'cart', 'checkout'];

    /**
     * Field key of the placement widget. Never stored: expandExpressPlacements()
     * removes it and writes button_{location} instead.
     */
    protected static $expressPlacementsKey = 'button_pages';

    /** Stored key for the standard express placement contract. */
    public const EXPRESS_SHOW_ON_KEY = 'express_show_on';

    abstract protected function expressSettingsSpec(): array;

    /**
     * Rebuild form_fields into the standardised express layout.
     */
    protected function applyExpressSettings(): void
    {
        $spec = $this->expressSettingsSpec();

        $fields = $this->expressDefaultSection($spec);

        $fields += $this->expressSection(
            'express_method_specific_title',
            __('Method specific settings', 'wc-buckaroo-bpe-gateway'),
            $this->expressMethodSpecificFields($spec)
        );

        $fields += $this->expressSection(
            'express_graphical_title',
            __('Graphical settings', 'wc-buckaroo-bpe-gateway'),
            $spec['graphical'] ?? []
        );

        $fields += $this->expressSection(
            'express_advanced_title',
            __('Advanced settings', 'wc-buckaroo-bpe-gateway'),
            $spec['advanced'] ?? []
        );

        $this->form_fields = $fields;

        add_filter(
            'woocommerce_settings_api_sanitized_fields_' . $this->id,
            [$this, 'expandExpressPlacements']
        );
    }

    /**
     * Default settings render without a heading, with the credentials between
     * Enable/Disable and Transaction mode.
     *
     * Anything in 'secondary_credentials' is appended after the default block
     * instead. WooCommerce renders a 'title' field as a bare <h3> with no
     * closing container, so a heading placed mid-block would read as owning
     * every field after it.
     */
    private function expressDefaultSection(array $spec): array
    {
        $credentials = $spec['credentials'] ?? [];
        $fields = [];

        foreach ($this->form_fields as $key => $field) {
            if ($key === 'mode') {
                $fields += $credentials;
                $credentials = [];
            }

            $fields[$key] = $field;
        }

        return $fields + $credentials + ($spec['secondary_credentials'] ?? []);
    }

    private function expressMethodSpecificFields(array $spec): array
    {
        $fields = [];

        if (! empty($spec['placements'])) {
            $fields[static::$expressPlacementsKey] = [
                'title' => __('Show the express button on', 'wc-buckaroo-bpe-gateway'),
                'type' => 'multiselect',
                'class' => 'wc-enhanced-select',
                'description' => __('Pages where the customer sees the express button. Clear all to hide it everywhere.', 'wc-buckaroo-bpe-gateway'),
                'options' => $this->expressPlacementOptions($spec['placements']),
                'default' => $this->expressPlacementsFromStorage(),
            ];
        }

        if (! empty($spec['list_as_payment_method'])) {
            $fields['checkout_method'] = [
                'title' => __('List as payment method in checkout', 'wc-buckaroo-bpe-gateway'),
                'type' => 'select',
                'description' => __('Also offer the method in the regular payment method list, next to the express button.', 'wc-buckaroo-bpe-gateway'),
                'options' => [
                    'TRUE' => __('Yes', 'wc-buckaroo-bpe-gateway'),
                    'FALSE' => __('No', 'wc-buckaroo-bpe-gateway'),
                ],
                'default' => 'TRUE',
            ];
        }

        return $fields + ($spec['behaviour'] ?? []);
    }

    private function expressPlacementOptions(array $locations): array
    {
        $labels = [
            'product' => __('Product page', 'wc-buckaroo-bpe-gateway'),
            'cart' => __('Cart page', 'wc-buckaroo-bpe-gateway'),
            'checkout' => __('Checkout page', 'wc-buckaroo-bpe-gateway'),
        ];

        return array_intersect_key($labels, array_flip($locations));
    }

    /**
     * Only emit the heading when the section has fields.
     */
    private function expressSection(string $key, string $title, array $fields): array
    {
        if ($fields === []) {
            return [];
        }

        return [$key => ['title' => $title, 'type' => 'title']] + $fields;
    }

    /**
     * Current placements, read from the legacy button_{location} keys.
     *
     * form_fields is built before init_settings() runs, so the stored option is
     * read directly. \get_option() is the WordPress function, not the gateway
     * method of the same name.
     */
    protected function expressPlacementsFromStorage(): array
    {
        $stored = \get_option($this->get_option_key(), []);
        $stored = is_array($stored) ? $stored : [];

        return $this->readExpressPlacements($stored);
    }

    /**
     * Where a method keeps its placements. The default is the legacy
     * button_{location} keys; a method storing them differently overrides this
     * and writeExpressPlacements().
     *
     * @param  array  $stored
     * @return array<string>
     */
    protected function readExpressPlacements(array $stored): array
    {
        // array_key_exists, never empty(): WooCommerce stores an emptied
        // multiselect as '' (validate_multiselect_field returns '' when nothing
        // is posted), and that means "nothing selected", not "not migrated".
        if ($this->usesExpressStorageKeys()) {
            return ExpressPlacements::fromSettings($stored);
        }

        $selected = [];

        foreach (static::$expressLocations as $location) {
            if (($stored['button_' . $location] ?? 'TRUE') === 'TRUE') {
                $selected[] = $location;
            }
        }

        return $selected;
    }

    /**
     * Whether this method stores the standard express contract under its
     * express_* keys. Gateways still on the legacy keys leave this false and
     * are read through the fallback above.
     */
    protected function usesExpressStorageKeys(): bool
    {
        return false;
    }

    /**
     * Placements currently in effect, for callers outside the settings screen.
     *
     * @return array<string>
     */
    public function expressPlacements(): array
    {
        return $this->expressPlacementsFromStorage();
    }

    public function isExpressPlacementEnabled(string $location): bool
    {
        return in_array($location, $this->expressPlacements(), true);
    }

    /**
     * @param  array<mixed>  $locations
     * @return array<string>
     */
    private function onlyKnownLocations(array $locations): array
    {
        return array_values(array_intersect(static::$expressLocations, $locations));
    }

    /**
     * @param  array  $settings
     * @param  array<string>  $selected
     * @return array
     */
    protected function writeExpressPlacements(array $settings, array $selected): array
    {
        $selected = $this->onlyKnownLocations($selected);

        if ($this->usesExpressStorageKeys()) {
            return ExpressPlacements::toSettings($settings, $selected);
        }

        foreach (static::$expressLocations as $location) {
            $settings['button_' . $location] = in_array($location, $selected, true) ? 'TRUE' : 'FALSE';
        }

        return $settings;
    }

    /**
     * Expand the placement widget back into the legacy keys on save, so stored
     * settings keep the shape every read site already expects.
     *
     * @param  array  $settings
     * @return array
     */
    public function expandExpressPlacements($settings)
    {
        if (! is_array($settings) || ! isset($settings[static::$expressPlacementsKey])) {
            return $settings;
        }

        $selected = (array) $settings[static::$expressPlacementsKey];

        $settings = $this->writeExpressPlacements($settings, $selected);

        unset($settings[static::$expressPlacementsKey]);

        return $settings;
    }
}
