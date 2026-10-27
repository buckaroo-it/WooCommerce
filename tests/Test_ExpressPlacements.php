<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\Express\ExpressPlacements;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance criteria for the Apple Pay express key move:
 *  - placement after update matches placement before, all-Hide included
 *  - an install that skips the upgrade routine renders in the same places
 */
class Test_ExpressPlacements extends TestCase
{
    // --- reads: new key wins, legacy is the fallback ---

    public function test_reads_the_new_key_when_present()
    {
        $this->assertSame(
            ['product', 'checkout'],
            ExpressPlacements::fromSettings(['express_show_on' => ['checkout', 'product']])
        );
    }

    /**
     * The case the whole design turns on. WooCommerce stores an emptied
     * multiselect as '' rather than [], and the legacy keys' absent value reads
     * as shown - so treating '' as "unmigrated" would switch a deliberately
     * hidden button back on everywhere.
     */
    public function test_all_hide_stored_as_empty_string_stays_hidden()
    {
        $this->assertSame([], ExpressPlacements::fromSettings(['express_show_on' => '']));
    }

    public function test_all_hide_stored_as_empty_array_stays_hidden()
    {
        $this->assertSame([], ExpressPlacements::fromSettings(['express_show_on' => []]));
    }

    public function test_unknown_locations_are_discarded()
    {
        $this->assertSame(
            ['cart'],
            ExpressPlacements::fromSettings(['express_show_on' => ['cart', 'minicart', 'none']])
        );
    }

    public function test_order_follows_the_canonical_location_order()
    {
        $this->assertSame(
            ['product', 'cart', 'checkout'],
            ExpressPlacements::fromSettings(['express_show_on' => ['checkout', 'cart', 'product']])
        );
    }

    // --- the skipped-upgrade path: legacy keys only ---

    public function test_falls_back_to_legacy_keys_when_the_new_key_is_absent()
    {
        $this->assertSame(
            ['product', 'checkout'],
            ExpressPlacements::fromSettings([
                'button_product' => 'TRUE',
                'button_cart' => 'FALSE',
                'button_checkout' => 'TRUE',
            ])
        );
    }

    public function test_legacy_all_hide_is_preserved()
    {
        $this->assertSame([], ExpressPlacements::fromSettings([
            'button_product' => 'FALSE',
            'button_cart' => 'FALSE',
            'button_checkout' => 'FALSE',
        ]));
    }

    // --- writes: dual-write keeps legacy consumers correct ---

    public function test_write_sets_both_the_new_key_and_the_legacy_keys()
    {
        $saved = ExpressPlacements::toSettings([], ['product', 'checkout']);

        $this->assertSame(['product', 'checkout'], $saved['express_show_on']);
        $this->assertSame('TRUE', $saved['button_product']);
        $this->assertSame('FALSE', $saved['button_cart']);
        $this->assertSame('TRUE', $saved['button_checkout']);
    }

    public function test_write_of_an_empty_selection_hides_everywhere_in_both_shapes()
    {
        $saved = ExpressPlacements::toSettings([], []);

        $this->assertSame([], $saved['express_show_on']);
        $this->assertSame(
            ['FALSE', 'FALSE', 'FALSE'],
            [$saved['button_product'], $saved['button_cart'], $saved['button_checkout']]
        );
    }

    public function test_write_then_read_round_trips()
    {
        foreach ([[], ['cart'], ['product', 'cart', 'checkout']] as $selection) {
            $this->assertSame(
                $selection,
                ExpressPlacements::fromSettings(ExpressPlacements::toSettings([], $selection))
            );
        }
    }

    // --- the storefront entry points, which read the option themselves ---

    /**
     * forGateway() builds the option name from the gateway id. That is the seam
     * every storefront read depends on - ApplepayButtons, PaymentSetupScripts
     * and InitGateways all reach placements through it - and injecting via
     * pre_option_ proves the name is built correctly, because a wrong key would
     * never reach the filter at all.
     */
    public function test_for_gateway_reads_the_settings_option_of_that_gateway()
    {
        $this->assertSame(
            ['cart'],
            $this->placementsFor('buckaroo_applepay', ['express_show_on' => ['cart']])
        );

        // An install that skipped the upgrade routine still resolves.
        $this->assertSame(
            ['product', 'checkout'],
            $this->placementsFor('buckaroo_googlepay', [
                'button_product' => 'TRUE',
                'button_cart' => 'FALSE',
                'button_checkout' => 'TRUE',
            ])
        );

        // Nothing stored at all: the legacy default is "shown".
        $this->assertSame(
            ['product', 'cart', 'checkout'],
            $this->placementsFor('buckaroo_applepay', [])
        );
    }

    public function test_enabled_for_answers_one_location_at_a_time()
    {
        $stored = ['express_show_on' => ['product', 'checkout']];

        $this->assertTrue($this->enabledFor('buckaroo_applepay', 'product', $stored));
        $this->assertFalse($this->enabledFor('buckaroo_applepay', 'cart', $stored));
        $this->assertTrue($this->enabledFor('buckaroo_applepay', 'checkout', $stored));
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return array<string>
     */
    private function placementsFor(string $gatewayId, array $stored): array
    {
        return $this->withStoredSettings($gatewayId, $stored, static function () use ($gatewayId) {
            return ExpressPlacements::forGateway($gatewayId);
        });
    }

    /**
     * @param  array<string, mixed>  $stored
     */
    private function enabledFor(string $gatewayId, string $location, array $stored): bool
    {
        return $this->withStoredSettings($gatewayId, $stored, static function () use ($gatewayId, $location) {
            return ExpressPlacements::enabledFor($gatewayId, $location);
        });
    }

    /**
     * Serves the settings option from a filter, so the options table is never
     * touched and the tests stay order independent.
     *
     * @param  array<string, mixed>  $stored
     * @return mixed
     */
    private function withStoredSettings(string $gatewayId, array $stored, callable $read)
    {
        if (! function_exists('add_filter')) {
            $this->markTestSkipped('WordPress not available');
        }

        $hook = 'pre_option_woocommerce_' . $gatewayId . '_settings';
        $inject = static function () use ($stored) {
            return $stored;
        };

        add_filter($hook, $inject);

        try {
            return $read();
        } finally {
            remove_filter($hook, $inject);
        }
    }
}
