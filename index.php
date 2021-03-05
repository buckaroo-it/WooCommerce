<?php
/*
Plugin Name: WC Buckaroo BPE Gateway
Plugin URI: http://www.buckaroo.nl
Author: Buckaroo
Author URI: http://www.buckaroo.nl
Description: Buckaroo payment system plugin for WooCommerce.
Version: 2.18.0
Text Domain: wc-buckaroo-bpe-gateway
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/
add_action('wp_enqueue_scripts', function (){
    wp_enqueue_style('buckaroo-custom-styles', plugin_dir_url( __FILE__ ) . 'library/css/buckaroo-custom.css');
    wp_enqueue_script('creditcard_capture', plugin_dir_url( __FILE__ ) . 'library/js/9yards/creditcard-capture-form.js', array('jquery'), '1.0.0', true );
});

add_action('plugins_loaded', 'buckaroo_init_gateway', 0);
add_action('admin_menu', 'buckaroo_menu_report');

if (!empty($_REQUEST['wc-api']) && ($_REQUEST['wc-api'] == 'WC_Push_Buckaroo')) {
    if (empty($_SERVER['HTTP_USER_AGENT'])) {
        $_SERVER['HTTP_USER_AGENT']='Buckaroo plugin push';
    }
    if (empty($_SERVER['HTTP_REFERER'])) {
        $_SERVER['HTTP_REFERER']='Buckaroo plugin referer';
    }
}

add_action('woocommerce_api_wc_push_buckaroo', 'buckaroo_push_class_init');

add_action( 'woocommerce_admin_order_actions_end', 'my_custom_checkout_field_display_admin_order_meta', 10, 1 );
add_action( 'wp_ajax_order_capture', 'orderCapture' );

include( plugin_dir_path(__FILE__) . 'includes/admin/meta-boxes/class-wc-meta-box-order-capture.php');

// Define BK_PLUGIN_FILE.
if ( ! defined( 'BK_PLUGIN_FILE' ) ) {
	define( 'BK_PLUGIN_FILE', __FILE__ );
}

include_once('install/class-wcb-install.php');

// Include the main Buckaroo class.
if ( ! class_exists( 'Buckaroo' ) ) {
	include_once dirname( __FILE__ ) . '/includes/class-buckaroo.php';
}

/**
 * Returns the main instance of WC.
 *
 * @return Buckaroo
 */
function BK() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	return Buckaroo::instance();
}

// Global for backwards compatibility.
$GLOBALS['buckaroo'] = BK();

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
        'Nexi' => array('filename' =>
            'gateway-buckaroo-nexi.php',
            'classname' => 'WC_Gateway_Buckaroo_Nexi',
        ),
        'P24' => array('filename' =>
            'gateway-buckaroo-p24.php',
            'classname' => 'WC_Gateway_Buckaroo_P24',
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
        'AfterPayNew' => array(
            'filename' => 'gateway-buckaroo-afterpaynew.php',
            'classname' => 'WC_Gateway_Buckaroo_AfterPaynew',
        ),
        'Payconiq' => array(
            'filename' => 'gateway-buckaroo-payconiq.php',
            'classname' => 'WC_Gateway_Buckaroo_Payconiq',
        ),
        'PaymentGuarantee' => array(
            'filename' => 'gateway-buckaroo-paygarant.php',
            'classname' => 'WC_Gateway_Buckaroo_PayGarant',
        ),
        'Applepay' => array(
            'filename' => 'gateway-buckaroo-applepay.php',
            'classname' => 'WC_Gateway_Buckaroo_Applepay',
        ),
        'KBC' => array(
            'filename' => 'gateway-buckaroo-kbc.php',
            'classname' => 'WC_Gateway_Buckaroo_KBC',
        ),
        'RequestToPay' => array(
            'filename' => 'gateway-buckaroo-requesttopay.php',
            'classname' => 'WC_Gateway_Buckaroo_RequestToPay',
        ),
        'In3' => array(
            'filename' => 'gateway-buckaroo-in3.php',
            'classname' => 'WC_Gateway_Buckaroo_In3',
        ),
        'Billink' => array(
            'filename' => 'gateway-buckaroo-billink.php',
            'classname' => 'WC_Gateway_Buckaroo_Billink',
        ),
        'PayPerEmail' => array(
            'filename' => 'gateway-buckaroo-payperemail.php',
            'classname' => 'WC_Gateway_Buckaroo_PayPerEmail',
        ),
        'KlarnaPay' => array(
            'filename' => 'gateway-buckaroo-klarnapay.php',
            'classname' => 'WC_Gateway_Buckaroo_KlarnaPay',
        ),
        'KlarnaPII' => array(
            'filename' => 'gateway-buckaroo-klarnapii.php',
            'classname' => 'WC_Gateway_Buckaroo_KlarnaPII',
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

    require_once __DIR__ . '/library/wp-actions/ApplePayButtons.php';
    (new ApplePayButtons)->loadActions();   
    
    add_filter('woocommerce_payment_gateways', 'add_buckaroo_gateway');
    new WC_Gateway_Buckaroo();

    if (!file_exists(__DIR__.'/../../../.well-known/apple-developer-merchantid-domain-association')) {
        if (!file_exists(__DIR__.'/../../../.well-known')) {
            mkdir(__DIR__.'/../../../.well-known', 0775, true);
        }
        
        copy(__DIR__.'/assets/apple-developer-merchantid-domain-association', __DIR__.'/../../../.well-known/apple-developer-merchantid-domain-association');
    }

    function add_buckaroo_send_admin_payperemail( $actions ) {
        global $theorder;
        if(BuckarooConfig::get('enabled','payperemail') == 'yes'){
            if (in_array($theorder->get_status(), array('auto-draft', 'pending', 'on-hold'))) {
                if(BuckarooConfig::get('show_PayPerEmail','payperemail') == 'TRUE'){
                    $actions['buckaroo_send_admin_payperemail'] = __( 'Send a PayPerEmail', 'woocommerce' );
                }
            }
            if (in_array($theorder->get_status(), array('pending', 'pending', 'on-hold', 'failed'))) {
                if(BuckarooConfig::get('show_PayLink','payperemail') == 'TRUE'){
                    $actions['buckaroo_create_paylink'] = __( 'Create PayLink', 'woocommerce' );
                }
            }
        }
        return $actions;
    }
    add_filter( 'woocommerce_order_actions', 'add_buckaroo_send_admin_payperemail', 10, 1 );

    require_once(dirname(__FILE__) . '/gateway-buckaroo-payperemail.php');
    function buckaroo_send_admin_payperemail( $order ) {
        $gateway = new WC_Gateway_Buckaroo_PayPerEmail();
        if (isset($gateway)) {
            $response = $gateway->process_payment($order->get_id());
            echo json_encode($response);
        }
    }
    add_action( 'woocommerce_order_action_buckaroo_send_admin_payperemail', 'buckaroo_send_admin_payperemail', 10, 1 );

    function buckaroo_create_paylink( $order ) {
        $gateway = new WC_Gateway_Buckaroo_PayPerEmail();
        if (isset($gateway)) {
            $response = $gateway->process_payment($order->get_id(),1);
        }
    }

    add_action( 'woocommerce_order_action_buckaroo_create_paylink', 'buckaroo_create_paylink', 10, 1 );

}

function my_custom_checkout_field_display_admin_order_meta($order){

}

function orderCapture(){

    $paymentMethod = get_post_meta( $_POST['order_id'], '_wc_order_selected_payment_method', true);

    switch ($paymentMethod) {
        case "Afterpay":
            require_once(dirname(__FILE__) . '/gateway-buckaroo-afterpay.php');
            $gateway = new WC_Gateway_Buckaroo_Afterpay();            
            break;
        case "Afterpaynew":
            require_once(dirname(__FILE__) . '/gateway-buckaroo-afterpaynew.php');
            $gateway = new WC_Gateway_Buckaroo_Afterpaynew();            
            break;
        case "Creditcard":
            require_once(dirname(__FILE__) . '/gateway-buckaroo-creditcard.php');
            $gateway = new WC_Gateway_Buckaroo_Creditcard();
            break;                                
    }
   
    if (isset($gateway)) {
        $response = $gateway->process_capture($_POST);
        echo json_encode($response);
    }
    exit;
}

/**
 * Admin notice
 * types: error,warning,success,info
 */
function buckaroo_admin_notice() {
    if($message = get_transient( get_current_user_id().'buckarooAdminNotice' ) ) {
        delete_transient( get_current_user_id().'buckarooAdminNotice' );
        echo '<div class="notice notice-'.$message['type'].' is-dismissible"><p>'.$message['message'].'</p></div>';
    }
}
add_action('admin_notices', 'buckaroo_admin_notice');

//test
 
