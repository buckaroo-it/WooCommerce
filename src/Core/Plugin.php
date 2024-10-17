<?php

namespace Buckaroo\Woocommerce\Core;

use Buckaroo\Woocommerce\Gateways\Applepay\ApplepayButtons;
use Buckaroo\Woocommerce\Hooks\HookRegistry;
use Buckaroo\Woocommerce\Install\Install;

/**
 * Class Plugin
 *
 * Main class responsible for initializing and registering plugin components.
 */
class Plugin
{
    /**
     * Instance of PaymentGatewayRegistry.
     *
     * @var PaymentGatewayRegistry
     */
    protected PaymentGatewayRegistry $gatewayRegistry;

    /**
     * Plugin constructor.
     *
     * Initializes the PaymentGatewayRegistry.
     */
    public function __construct()
    {
        $this->gatewayRegistry = new PaymentGatewayRegistry();
    }

    public static function init(): void
    {
        new HookRegistry();
    }

    public function register()
    {
        $this->ensureWooCommerceIsActive();

        $this->loadTextDomain();
        $this->registerGateways();
        $this->registerAfterpayButtons();
        $this->ensureAppleDeveloperDomainAssociation();

        $this->performInstallation();

        return $this;
    }

    /**
     * Ensure that WooCommerce is active.
     *
     * If WooCommerce is not active, sets a transient to notify the user.
     *
     * @return void
     */
    protected function ensureWooCommerceIsActive(): void
    {
        if (!class_exists('WC_Order')) {
            $transientKey = get_current_user_id() . '_buckaroo_require_woocommerce';
            set_transient($transientKey, true, HOUR_IN_SECONDS);

            return;
        }

        $transientKey = get_current_user_id() . '_buckaroo_require_woocommerce';
        delete_transient($transientKey);
    }

    /**
     * Load the plugin's text domain for translations.
     *
     * @return void
     */
    protected function loadTextDomain(): void
    {
        load_plugin_textdomain(
            'wc-buckaroo-bpe-gateway',
            false,
            dirname(plugin_basename(BK_PLUGIN_FILE)) . '/languages/'
        );
    }

    /**
     * Register payment gateways with WooCommerce.
     *
     * @return void
     */
    protected function registerGateways(): void
    {
        $this->gatewayRegistry->load();

        add_filter(
            'woocommerce_payment_gateways',
            [$this->gatewayRegistry, 'hookGatewaysToWooCommerce']
        );
    }

    /**
     * Register Afterpay buttons.
     *
     * @return void
     */
    protected function registerAfterpayButtons(): void
    {
        $afterpayButtons = new ApplepayButtons();
        $afterpayButtons->loadActions();
    }

    /**
     * Ensure the Apple Developer Domain Association file exists.
     *
     * Creates the necessary directories and copies the association file if it doesn't exist.
     *
     * @return void
     */
    protected function ensureAppleDeveloperDomainAssociation(): void
    {
        $destinationDir = ABSPATH . '.well-known';
        $destinationFile = $destinationDir . '/apple-developer-merchantid-domain-association';
        $sourceFile = plugin_dir_path(BK_PLUGIN_FILE) . 'assets/apple-developer-merchantid-domain-association';

        if (!file_exists($destinationFile)) {
            if (!is_dir($destinationDir)) {
                if (!mkdir($destinationDir, 0775, true) && !is_dir($destinationDir)) {
                    // Handle the error appropriately, e.g., log it or throw an exception
                    error_log("Failed to create directory: {$destinationDir}");
                    return;
                }
            }

            if (!copy($sourceFile, $destinationFile)) {
                // Handle the error appropriately, e.g., log it or throw an exception
                error_log("Failed to copy {$sourceFile} to {$destinationFile}");
            }
        }
    }

    /**
     * Perform installation routines if necessary.
     *
     * This method ensures that any required installation steps are executed,
     * especially for installations prior to version 2.24.1.
     *
     * @return void
     */
    protected function performInstallation(): void
    {
        Install::installUntrackedInstalation();
    }

    /**
     * Get the PaymentGatewayRegistry instance.
     *
     * @return PaymentGatewayRegistry
     */
    public function getGatewayRegistry(): PaymentGatewayRegistry
    {
        return $this->gatewayRegistry;
    }
}