<?php
/*
Plugin Name: WC Buckaroo BPE Gateway
Plugin URI: http://www.buckaroo.nl
Author: Buckaroo
Author URI: http://www.buckaroo.nl
Description: Buckaroo payment system plugin for WooCommerce.
Version: 2.24.1
Text Domain: wc-buckaroo-bpe-gateway
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/
add_action( 'admin_enqueue_scripts', 'buckaroo_payment_setup_scripts' );

/**
 * Enqueue backend scripts
 *
 * @return void
 */
function buckaroo_payment_setup_scripts()
{
    wp_enqueue_style(
        'buckaroo-custom-styles',
        plugin_dir_url( __FILE__ ) . 'library/css/buckaroo-custom.css',
        [],
        BuckarooConfig::VERSION
    );

    wp_enqueue_script(
        'initiate_jquery_if_not_loaded',
        plugin_dir_url(__FILE__) . 'library/js/loadjquery.js',
        ['jquery'],
        BuckarooConfig::VERSION,
        true
    );
    wp_enqueue_script(
        'creditcard_capture',
        plugin_dir_url( __FILE__ ) . 'library/js/9yards/creditcard-capture-form.js',
        array('jquery'),
        BuckarooConfig::VERSION,
        true
    );
    wp_enqueue_script(
        'buckaroo_certificate_management_js',
        plugin_dir_url(__FILE__) . 'library/js/9yards/upload_certificate.js',
        ['jquery'],
        BuckarooConfig::VERSION,
        true
    );
    wp_enqueue_script(
        'buckaroo_display_local_settings',
        plugin_dir_url(__FILE__) . 'library/js/9yards/display_local.js',
        ['jquery'],
        BuckarooConfig::VERSION,
        true
    );
}
add_action('wp_enqueue_scripts', 'buckaroo_payment_frontend_scripts');

/**
 * Enqueue frontend scripts
 *
 * @return void
 */
function buckaroo_payment_frontend_scripts() 
{
    wp_enqueue_style(
        'buckaroo-custom-styles',
        plugin_dir_url( __FILE__ ) . 'library/css/buckaroo-custom.css',
        [],
        BuckarooConfig::VERSION
    );
    
    wp_enqueue_script(
        'initiate_jquery_if_not_loaded',
        plugin_dir_url(__FILE__) . 'library/js/loadjquery.js',
        ['jquery'],
        BuckarooConfig::VERSION,
        true
    );
    wp_enqueue_script(
        'creditcard_encryption_sdk',
        plugin_dir_url(__FILE__) . 'library/js/9yards/creditcard-encryption-sdk.js',
        ['jquery'],
        BuckarooConfig::VERSION,
        true
    );
    wp_enqueue_script(
        'creditcard_call_encryption',
        plugin_dir_url(__FILE__) . 'library/js/9yards/creditcard-call-encryption.js',
        ['jquery'],
        BuckarooConfig::VERSION,
        true
    );

    if (is_checkout()) {
        wp_enqueue_script(
            'wc-pf-checkout',
            plugin_dir_url(__FILE__) . '/assets/js/checkout.js',
            ['jquery'],
            BuckarooConfig::VERSION,
            true
        );
    }
}
add_action('plugins_loaded', 'buckaroo_init_gateway', 0);

if (!empty($_REQUEST['wc-api']) && ($_REQUEST['wc-api'] == 'WC_Push_Buckaroo')) {
    if (empty($_SERVER['HTTP_USER_AGENT'])) {
        $_SERVER['HTTP_USER_AGENT']='Buckaroo plugin push';
    }
    if (empty($_SERVER['HTTP_REFERER'])) {
        $_SERVER['HTTP_REFERER']='Buckaroo plugin referer';
    }
}
add_action( 'upgrader_process_complete', 'copy_language_files');

function copy_language_files(){
    foreach (glob(__DIR__ . '/languages/*.{po,mo}', GLOB_BRACE) as $file) {
        if(!is_dir($file) && is_readable($file)) {
            $dest = WP_CONTENT_DIR . '/languages/plugins/' . basename($file);
            rename($file, $dest);
        }
    }
}

add_action('woocommerce_api_wc_push_buckaroo', 'buckaroo_push_class_init');

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


function generateGateways()
{

    $buckaroo_enabled_payment_methods_pre = array(
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
        'PostePay' => array('filename' =>
            'gateway-buckaroo-postepay.php',
            'classname' => 'WC_Gateway_Buckaroo_PostePay',
        ),
        'P24' => array('filename' =>
            'gateway-buckaroo-p24.php',
            'classname' => 'WC_Gateway_Buckaroo_P24',
        ),
        'Sofortbanking' => array(
            'filename' => 'gateway-buckaroo-sofort.php',
            'classname' => 'WC_Gateway_Buckaroo_Sofortbanking',
        ),
        'Belfius' => array(
            'filename' => 'gateway-buckaroo-belfius.php',
            'classname' => 'WC_Gateway_Buckaroo_Belfius',
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
        'EPS' => array(
            'filename' => 'gateway-buckaroo-eps.php',
            'classname' => 'WC_Gateway_Buckaroo_EPS',
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

function buckaroo_page_menu()
{
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
        __('Settings',  'wc-buckaroo-bpe-gateway'),
        __('Settings',  'wc-buckaroo-bpe-gateway'),
        'manage_options',
        'admin.php?page=wc-settings&tab=buckaroo_settings',
    );
    add_submenu_page(
        'admin.php?page=wc-settings&tab=buckaroo_settings',
        __('Payment methods',  'wc-buckaroo-bpe-gateway'),
        __('Payment methods',  'wc-buckaroo-bpe-gateway'),
        'manage_options',
        'admin.php?page=wc-settings&tab=buckaroo_settings&section=methods',
    );
    add_submenu_page(
        'admin.php?page=wc-settings&tab=buckaroo_settings',
        __('Report',  'wc-buckaroo-bpe-gateway'),
        __('Report',  'wc-buckaroo-bpe-gateway'),
        'manage_options',
        'admin.php?page=wc-settings&tab=buckaroo_settings&section=report',
    );
}

/**
 * Add link to plugin settings in plugin list
 * plugin_action_links_'.plugin_basename(__FILE__)
 *
 * @param array $actions
 *
 * @return array $actions
 */
function buckaroo_add_setting_link($actions)
{
    $settingsLink = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=buckaroo_settings') . '">'.__('Settings',  'wc-buckaroo-bpe-gateway').'</a>',
    );
    $actions = array_merge($actions, $settingsLink);
    return $actions;
}
/**
 * Add the buckaroo tab to woocommerce settings page
 *
 * @param array $settings Array of woocommerce tabs
 *
 * @return array $settings Array of woocommerce tabs
 */
function buckaroo_add_woocommerce_settings_page($settings)
{
    include_once __DIR__.'/templates/BuckarooReportPage.php';
    include_once __DIR__.'/gateway-buckaroo-mastersettings.php';
    $settings[] = include_once plugin_dir_path(__FILE__). "WC_Buckaroo_Settings_Page.php";
    return $settings;
}
function buckaroo_init_gateway()
{
    add_filter(
        'plugin_action_links_'.plugin_basename(__FILE__), 'buckaroo_add_setting_link'
    );
    add_filter(
        'woocommerce_get_settings_pages', 'buckaroo_add_woocommerce_settings_page'
    );
    add_action('admin_menu', 'buckaroo_page_menu');

    require_once 'library/include.php';
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

    require_once __DIR__ . '/controllers/IdinController.php';

    $idinController = new IdinController;

    add_action('woocommerce_before_single_product','buckaroo_idin_product');
    add_action('woocommerce_before_cart','buckaroo_idin_cart');
    add_action('woocommerce_review_order_before_payment','buckaroo_idin_checkout');

    add_action('woocommerce_api_wc_gateway_buckaroo_idin-identify', [$idinController, 'identify']);
    add_action('woocommerce_api_wc_gateway_buckaroo_idin-reset', [$idinController, 'reset']);
    add_action('woocommerce_api_wc_gateway_buckaroo_idin-return', [$idinController, 'returnHandler']);
}

function buckaroo_idin_product() {
    global $post;

    if (BuckarooConfig::isIdin([$post->ID])) {
        include 'templates/idin/cart.php';
    }
}

function buckaroo_idin_cart() {
    if (BuckarooConfig::isIdin(BuckarooIdin::getCartProductIds())) {
        include 'templates/idin/cart.php';
    }
}

function buckaroo_idin_checkout() {
    if (BuckarooConfig::isIdin(BuckarooIdin::getCartProductIds())) {
        include 'templates/idin/checkout.php';
    }
}

/**
 * Ajax hook for capture of orders 
 *
 * @return void
 */
function orderCapture()
{

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
        echo json_encode(
            $gateway->process_capture($_POST)
        );
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
 
