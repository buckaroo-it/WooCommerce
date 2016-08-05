<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WC_Install Class
 */
class WC_Buckaroo_Install {


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
) $collate;
        " );
        return true;

    }


}
