<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Trait for Subscriptions utility functions.
 */
trait WC_Buckaroo_Subscriptions_Trait {

    public function addSubscriptionsSupport(){
        if ( ! $this->is_subscriptions_enabled() ) {
            return;
        }

        $this->supports = array_merge(
            $this->supports,
            [
                'subscriptions',
                'subscription_cancellation',
                'subscription_suspension',
                'subscription_reactivation',
            ]
        );
    }

    /**
     * Checks if subscriptions are enabled on the site.
     *
     * @return bool Whether subscriptions is enabled or not.
     */
    public function is_subscriptions_enabled() {
        return class_exists( 'WC_Subscriptions' ) && version_compare( WC_Subscriptions::$version, '2.2.0', '>=' ) && is_plugin_active( 'WooCommerce_Subscriptions/buckaroo-subscriptions.php');
    }

    /**
     * Is $order_id a subscription?
     *
     * @param  int $order_id
     * @return boolean
     */
    public function has_subscription( $order_id ) {
        return ( function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $order_id ) || wcs_is_subscription( $order_id ) || wcs_order_contains_renewal( $order_id ) ) );
    }
}