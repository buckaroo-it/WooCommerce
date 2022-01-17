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
    public static function install()
    {
        global $wpdb;

        $wpdb->hide_errors();

        $collate = self::getCollate();

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
        self::create_log_table();

        return true;
    }
    /**
     * Create table for logs if not exists
     *
     * @return void
     */
    public static function create_log_table()
    {
        global $wpdb;

        $wpdb->hide_errors();
        $table = $wpdb->prefix.Buckaroo_Logger_Storage::STORAGE_DB_TABLE;
        $collate = self::getCollate();

        $wpdb->query(
            "CREATE TABLE IF NOT EXISTS $table (
                `id` BIGINT NOT NULL AUTO_INCREMENT , 
                `date` DATETIME NOT NULL , 
                `message` TEXT NOT NULL , 
                `location_id` VARCHAR(255) NOT NULL , 
                PRIMARY KEY (`id`)
            )  $collate;" 
        );
    }
    public static function getCollate()
    {
        global $wpdb;
        
        $collate = '';

        if ( $wpdb->has_cap( 'collation' ) ) {
            if ( ! empty( $wpdb->charset ) ) {
                $collate .= "DEFAULT CHARACTER SET $wpdb->charset";
            }
            if ( ! empty( $wpdb->collate ) ) {
                $collate .= " COLLATE $wpdb->collate";
            }
        }
        return $collate;
    }
}