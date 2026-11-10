<?php

namespace Buckaroo\Woocommerce\Services;

use Buckaroo\Woocommerce\Core\Plugin;
use BuckarooDeps\Buckaroo\Adapter\Upg\UpgSetup;
use BuckarooDeps\Buckaroo\Upg\AppInfo;
use BuckarooDeps\Buckaroo\Upg\BuckarooUpgClient;
use BuckarooDeps\Buckaroo\Upg\ClientOptions;
use BuckarooDeps\Buckaroo\Upg\Exception\AuthenticationException;
use BuckarooDeps\Buckaroo\Upg\Mode;
use Throwable;

/**
 * The merchant's UPG choice, and what the plugin stores for it when the settings are saved.
 *
 * UPG keys work in one mode only (sandbox or live), so the methods and the webhook secret are stored per mode.
 */
class UpgSettings
{
    /** Not autoloaded: it holds the webhook secrets. */
    public const STATE_OPTION = 'woocommerce_buckaroo_upg_state';

    public const WEBHOOK_ACTION = 'wc_push_buckaroo_upg';

    private const MASTER_OPTION = 'woocommerce_buckaroo_mastersettings_settings';

    private const MODES = ['test', 'live'];

    public static function hasKey(string $mode): bool
    {
        return self::key(self::mode($mode)) !== '';
    }

    public static function isChosen(): bool
    {
        return (self::master()['api'] ?? 'bpe') === 'upg';
    }

    /**
     * The UPG client for a mode, or null without a key. The `buckaroo_upg_client` filter can replace it,
     * for example with one that uses another HTTP client.
     */
    public static function client(string $mode): ?BuckarooUpgClient
    {
        $mode = self::mode($mode);
        if (! self::hasKey($mode)) {
            return null;
        }
        $key = self::key($mode);

        $client = new BuckarooUpgClient($key, new ClientOptions(
            mode: $mode === 'live' ? Mode::Live : Mode::Test,
            appInfo: new AppInfo('WooCommerce Buckaroo Payments', Plugin::VERSION),
        ));

        return apply_filters('buckaroo_upg_client', $client, $mode);
    }

    /**
     * The BPE method codes to send to UPG in a mode: the ones stored on the last settings save, while UPG is
     * chosen and the mode has a key. Otherwise none, so every payment goes to the BPE API.
     *
     * @return string[]
     */
    public static function methods(string $mode): array
    {
        $mode = self::mode($mode);
        if (! self::isChosen() || ! self::hasKey($mode)) {
            return [];
        }

        return array_values(array_filter(self::state()[$mode]['methods'] ?? [], 'is_string'));
    }

    /**
     * The stored webhook secrets of both modes. The webhook check takes them all, because the request cannot say
     * its mode in a signed way.
     *
     * @return string[]
     */
    public static function webhookSecrets(): array
    {
        $secrets = [];
        foreach (self::MODES as $mode) {
            $secret = self::state()[$mode]['webhook_secret'] ?? '';
            if (is_string($secret) && $secret !== '') {
                $secrets[] = $secret;
            }
        }

        return $secrets;
    }

    public static function webhookUrl(): string
    {
        return add_query_arg('wc-api', self::WEBHOOK_ACTION, home_url('/'));
    }

    /**
     * Runs on settings save with UPG chosen. For each mode with a key: checks the key, stores the methods UPG can
     * take, and registers the webhook. A failure becomes a message; it never blocks the save. Only the webhook marks
     * a UPG order paid, so a mode without one keeps its payments on BPE.
     *
     * @return string[] messages to show the merchant
     */
    public static function sync(): array
    {
        $errors = [];
        $state = self::state();

        foreach (self::MODES as $mode) {
            $client = self::client($mode);
            if ($client === null) {
                unset($state[$mode]);
                continue;
            }

            $setup = new UpgSetup($client);
            try {
                $state[$mode]['methods'] = $setup->availableMethods();
            } catch (AuthenticationException $e) {
                $state[$mode]['methods'] = [];
                /* translators: 1: test or live, 2: error from UPG */
                $errors[] = sprintf(__('The UPG %1$s API key does not work: %2$s', 'wc-buckaroo-bpe-gateway'), $mode, $e->getMessage());
                continue;
            } catch (Throwable $e) {
                /* translators: 1: test or live, 2: error from UPG */
                $errors[] = sprintf(__('The UPG %1$s payment methods could not be read, so the last saved ones stay in use: %2$s', 'wc-buckaroo-bpe-gateway'), $mode, $e->getMessage());
                continue;
            }

            try {
                $state[$mode]['webhook_secret'] = $setup->registerWebhook(self::webhookUrl(), $state[$mode]['webhook_secret'] ?? null);
            } catch (Throwable $e) {
                $state[$mode]['methods'] = [];
                /* translators: 1: test or live, 2: error from UPG */
                $errors[] = sprintf(__('The UPG %1$s webhook could not be registered, so payments keep using the Store key and Secret key: %2$s', 'wc-buckaroo-bpe-gateway'), $mode, $e->getMessage());
            }
        }

        update_option(self::STATE_OPTION, $state, false);

        return $errors;
    }

    private static function key(string $mode): string
    {
        return trim((string) (self::master()['upg_' . $mode . '_key'] ?? ''));
    }

    private static function mode(string $mode): string
    {
        return strtolower($mode) === 'live' ? 'live' : 'test';
    }

    private static function master(): array
    {
        $settings = get_option(self::MASTER_OPTION, []);

        return is_array($settings) ? $settings : [];
    }

    private static function state(): array
    {
        $state = get_option(self::STATE_OPTION, []);

        return is_array($state) ? $state : [];
    }
}
