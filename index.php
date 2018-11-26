<?php
/*
Plugin Name: WC Buckaroo BPE Gateway
Plugin URI: http://www.buckaroo.nl
Author: Buckaroo
Author URI: http://www.buckaroo.nl
Description: Buckaroo payment system plugin for WooCommerce.
Version: 2.8.1
Text Domain: wc-buckaroo-bpe-gateway
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/
add_action('plugins_loaded', 'buckaroo_init_gateway', 0);
add_action('admin_menu', 'buckaroo_menu_report');
add_action('woocommerce_api_wc_push_buckaroo', 'buckaroo_push_class_init');
include_once('install/class-wcb-install.php');

register_activation_hook(__FILE__, array('WC_Buckaroo_Install', 'install'));
add_shortcode('buckaroo_payconiq', 'fw_reserve_page_template');

function fw_reserve_page_template()
{
    if (!isset($_GET["invoicenumber"]) && !isset($_GET["transactionKey"]) && !isset($_GET["currency"]) && !isset($_GET["amount"])){
        // When no parameters, redirect to cart page.
        wc_add_notice( __( 'Checkout is not available whilst your cart is empty.', 'woocommerce' ), 'notice' );
        wp_safe_redirect( wc_get_page_permalink( 'cart' ) );
        exit;
    } else {
        include 'templates/payconiq/qrcode.php' ;
    }
    return ob_get_clean();
}

function buckaroo_push_class_init()
{
    new WC_Push_Buckaroo();
    exit();
}

function buckaroo_menu_report()
{
    add_menu_page('Buckaroo plugin report', 'Buckaroo report', 'manage_options', 'buckaroo-report', 'buckaroo_reports');
}

function buckaroo_reports()
{
    echo '<h1>Error report for Buckaroo WooCommerce plugin</h1>';
    echo '<table class="wp-list-table widefat fixed posts">
    <tr>
        <th width="5%"><b>Error no</b></th>
        <th width="15%"><b>Time</b></th>
        <th width="80%"><b>Error description</b></th>
    </tr>';
    $plugin_dir = plugin_dir_path(__FILE__);
    $file = $plugin_dir . 'library/api/log/report_log.txt';
    if (file_exists($file)) {
        $data = Array();
        $handle = @fopen($plugin_dir . 'library/api/log/report_log.txt', "r");
        if ($handle) {
            while (($buffer = fgets($handle, 4096)) !== false) {
                $data[] = $buffer;
            }
            fclose($handle);
        }
        if (!empty($data)) {
            $data = array_reverse($data);
            $i = 1;
            foreach ($data as $d) {
                $tmp = explode("|||", $d);
                if (!empty($tmp[1])) {
                    list ($time, $value) = $tmp;
                } else {
                    $time = 'unknown';
                    $value = $d;
                }
                echo '<tr>
                <td>' . $i . '</td>
                <td>' . $time . '</td>
                <td>' . $value . '</td>
                </tr>';
                $i++;
            }
        } else {
            echo '<tr>
        <td colspan="3">Log file empty</td>
        </tr>';

        }
    } else {
        echo '<tr>
        <td colspan="3">No data</td>
        </tr>';
    }

    echo '</table>';
}

function generateGateways()
{

    $buckaroo_enabled_payment_methods_pre = array(
        //Master Settings page should be left enabled
        'Master Settings' => array(
            'filename' => 'gateway-buckaroo-mastersettings.php',
            'classname' => 'WC_Gateway_Buckaroo_MasterSettings',
        ),
        //comment payment methods you do not want to use
        'PayPal' => array(
            'filename' => 'gateway-buckaroo-paypal.php',
            'classname' => 'WC_Gateway_Buckaroo_Paypal',),
        'iDeal' => array('filename' =>
            'gateway-buckaroo-ideal.php',
            'classname' => 'WC_Gateway_Buckaroo_Ideal',),
        'Creditcards' => array(
            'filename' => 'gateway-buckaroo-creditcard.php',
            'classname' => 'WC_Gateway_Buckaroo_Creditcard',
        ),
        'Bancontact / MisterCash' => array(
            'filename' => 'gateway-buckaroo-mistercash.php',
            'classname' => 'WC_Gateway_Buckaroo_MisterCash',
        ),
        'Giropay' => array('filename' =>
            'gateway-buckaroo-giropay.php',
            'classname' => 'WC_Gateway_Buckaroo_Giropay',),
        'Bank Transfer' => array(
            'filename' => 'gateway-buckaroo-transfer.php',
            'classname' => 'WC_Gateway_Buckaroo_Transfer',
        ),
        'Giftcards' => array('filename' =>
            'gateway-buckaroo-giftcard.php',
            'classname' => 'WC_Gateway_Buckaroo_Giftcard',
        ),
        'eMaestro' => array('filename' =>
            'gateway-buckaroo-emaestro.php',
            'classname' => 'WC_Gateway_Buckaroo_EMaestro',
        ),
        'Paysafecard' => array(
            'filename' => 'gateway-buckaroo-paysafecard.php',
            'classname' => 'WC_Gateway_Buckaroo_Paysafecard',
        ),
        'Sofortbanking' => array(
            'filename' => 'gateway-buckaroo-sofort.php',
            'classname' => 'WC_Gateway_Buckaroo_Sofortbanking',
        ),
        'SepaDirectDebit' => array(
            'filename' => 'gateway-buckaroo-sepadirectdebit.php',
            'classname' => 'WC_Gateway_Buckaroo_SepaDirectDebit',
        ),
        'AfterPay' => array(
            'filename' => 'gateway-buckaroo-afterpay.php',
            'classname' => 'WC_Gateway_Buckaroo_AfterPay',
        ),
        'Payconiq' => array(
            'filename' => 'gateway-buckaroo-payconiq.php',
            'classname' => 'WC_Gateway_Buckaroo_Payconiq',
        ),
        'PaymentGuarantee' => array(
            'filename' => 'gateway-buckaroo-paygarant.php',
            'classname' => 'WC_Gateway_Buckaroo_PayGarant',
        ),
    );
    $buckaroo_enabled_payment_methods = array();
    if (file_exists(dirname(__FILE__) . '/gateway-buckaroo-testscripts.php')) {
        $buckaroo_enabled_payment_methods['Test Scripts'] = array(
            'filename' => 'gateway-buckaroo-testscripts.php',
            'classname' => 'WC_Gateway_Buckaroo_TestScripts',
            );
    }
    if (file_exists(dirname(__FILE__) . '/buckaroo-exodus.php') && !get_option('woocommerce_buckaroo_exodus')) {
        $buckaroo_enabled_payment_methods['Exodus Script'] = array(
            'filename' => 'buckaroo-exodus.php',
            'classname' => 'WC_Gateway_Buckaroo_Exodus',
            );
    }
    foreach ($buckaroo_enabled_payment_methods_pre as $key => $value) {
        $buckaroo_enabled_payment_methods[$key] = $value;
    }
    return $buckaroo_enabled_payment_methods;
}

$buckaroo_enabled_payment_methods = generateGateways();

function buckaroo_init_gateway()
{
    load_plugin_textdomain('wc-buckaroo-bpe-gateway', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    global $buckaroo_enabled_payment_methods;
    $buckaroo_enabled_payment_methods = (count($buckaroo_enabled_payment_methods)) ? $buckaroo_enabled_payment_methods : generateGateways();
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }
    $plugin_dir = plugin_dir_path(__FILE__);

    foreach ($buckaroo_enabled_payment_methods as $method) {
        require_once $plugin_dir . $method['filename'];
    }
    require_once $plugin_dir . 'push-buckaroo.php';
    /**
     * Add the Gateway to WooCommerce
     **/
    function add_buckaroo_gateway($methods)
    {
        global $buckaroo_enabled_payment_methods;
        foreach ($buckaroo_enabled_payment_methods as $method) {
            $methods[] = $method['classname'];
        }
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_buckaroo_gateway');
    new WC_Gateway_Buckaroo();

}

