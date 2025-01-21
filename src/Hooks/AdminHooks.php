<?php

namespace Buckaroo\Woocommerce\Hooks;

use Buckaroo\Woocommerce\Admin\GeneralSettings;
use Buckaroo\Woocommerce\Admin\PaymentMethodSettings;

class AdminHooks {

    public function __construct() {
         add_action( 'admin_notices', array( $this, 'handleNotices' ) );
        add_action( 'admin_menu', array( $this, 'addPagesMenu' ) );
        add_action( 'woocommerce_get_settings_pages', array( $this, 'handlePages' ) );

        add_filter( 'plugin_action_links_' . plugin_basename( BK_PLUGIN_FILE ), array( $this, 'handleActionLinks' ) );
    }

    /**
     * Add link to plugin settings in plugin list
     * plugin_action_links_'.plugin_basename(__FILE__)
     *
     * @param array $actions
     *
     * @return array $actions
     */
    public function handleActionLinks( $actions ) {
         $settingsLink = array(
			 '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=buckaroo_settings' ) . '">' . esc_html__( 'Settings', 'wc-buckaroo-bpe-gateway' ) . '</a>',
		 );
		 $actions      = array_merge( $actions, $settingsLink );

		 return $actions;
    }


    /**
     * Add the buckaroo tab to woocommerce settings page
     *
     * @param array $settings Array of woocommerce tabs
     *
     * @return array $settings Array of woocommerce tabs
     */
    public function handlePages( $settings ) {
         $settings[] = new GeneralSettings( new PaymentMethodSettings() );

        return $settings;
    }

    public function addPagesMenu(): void {
        add_menu_page(
            'Buckaroo',
            'Buckaroo',
            'read',
            'admin.php?page=wc-settings&tab=buckaroo_settings',
            '',
            'data:image/svg+xml;base64,PHN2ZyB2ZXJzaW9uPSIxLjIiIGJhc2VQcm9maWxlPSJ0aW55LXBzIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAxNTAgMTUwIiB3aWR0aD0iMTUwIiBoZWlnaHQ9IjE1MCI+Cgk8dGl0bGU+bG9nby1zdmc8L3RpdGxlPgoJPHN0eWxlPgoJCXRzcGFuIHsgd2hpdGUtc3BhY2U6cHJlIH0KCQkuczAgeyBmaWxsOiAjY2RkOTA1IH0gCgk8L3N0eWxlPgoJPHBhdGggaWQ9IkxheWVyIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiIGNsYXNzPSJzMCIgZD0ibS0wLjA1IDAuODVoMjEuNGwxOS40NyA0My4wMWg2Mi4wOGwxOC40LTQzLjAxaDIxLjRsLTYyLjcxIDE0Ni44MmgtMTQuNzhsLTY1LjI4LTE0Ni44MnptOTQuODEgNjEuODVoLTQ1LjM3bDIzLjU0IDUyLjg3bDIxLjgzLTUyLjg3eiIgLz4KPC9zdmc+',
            '55.3'
        );
        add_submenu_page(
            'admin.php?page=wc-settings&tab=buckaroo_settings',
            esc_html__( 'Settings', 'wc-buckaroo-bpe-gateway' ),
            esc_html__( 'Settings', 'wc-buckaroo-bpe-gateway' ),
            'manage_options',
            'admin.php?page=wc-settings&tab=buckaroo_settings'
        );
        add_submenu_page(
            'admin.php?page=wc-settings&tab=buckaroo_settings',
            esc_html__( 'Payment methods', 'wc-buckaroo-bpe-gateway' ),
            esc_html__( 'Payment methods', 'wc-buckaroo-bpe-gateway' ),
            'manage_options',
            'admin.php?page=wc-settings&tab=buckaroo_settings&section=methods'
        );
        add_submenu_page(
            'admin.php?page=wc-settings&tab=buckaroo_settings',
            esc_html__( 'Report', 'wc-buckaroo-bpe-gateway' ),
            esc_html__( 'Report', 'wc-buckaroo-bpe-gateway' ),
            'manage_options',
            'admin.php?page=wc-settings&tab=buckaroo_settings&section=report'
        );
    }


    public function handleNotices(): void {
        if ( $message = get_transient( get_current_user_id() . 'buckarooAdminNotice' ) ) {
            delete_transient( get_current_user_id() . 'buckarooAdminNotice' );
            echo '<div class="notice notice-' . esc_attr( $message['type'] ) . ' is-dismissible"><p>' . wp_kses(
                $message['message'],
                array(
					'b' => array(),
					'p' => array(),
				)
            ) . '</p></div>';
        }
        if ( get_transient( get_current_user_id() . 'buckaroo_require_woocommerce' ) ) {
            delete_transient( get_current_user_id() . 'buckaroo_require_woocommerce' );
            echo '<div class="notice notice-error"><p>' . esc_html__(
                'Buckaroo BPE requires WooCommerce to be installed and active',
                'wc-buckaroo-bpe-gateway'
            ) . '</p></div>';
        }
    }
}
