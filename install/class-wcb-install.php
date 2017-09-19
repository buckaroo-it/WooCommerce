<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WC_Install Class
 * @package Buckaroo
 */
class WC_Buckaroo_Install {

    /**
     * @access public
     * @return boolean (true)
     */
    public static function install() {
        global $wpdb;

        $wpdb->hide_errors();

        $collate = '';

        if ( $wpdb->has_cap( 'collation' ) ) {
            if ( ! empty( $wpdb->charset ) ) {
                $collate .= "DEFAULT CHARACTER SET $wpdb->charset";
            }
            if ( ! empty( $wpdb->collate ) ) {
                $collate .= " COLLATE $wpdb->collate";
            }
        }

        $wpdb->query( "
            CREATE TABLE IF NOT EXISTS {$wpdb->prefix}woocommerce_buckaroo_transactions (
                wc_orderid bigint(20) NOT NULL,
                transaction varchar(200) NOT NULL,
                PRIMARY KEY  (wc_orderid)
            ) 
            $collate;" 
        );

        if (!get_option('woocommerce_buckaroo_exodus')) {
            add_option( 'woocommerce_buckaroo_exodus', 'a:1:{s:8:"covenant";b:1;}', '', 'yes' );
        } else {
            update_option('woocommerce_buckaroo_exodus', 'a:1:{s:8:"covenant";b:1;}', true);
        }
        return true;
    }
}