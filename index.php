<?php
/*
Plugin Name: WC Buckaroo BPE Gateway
Plugin URI: http://www.buckaroo.nl
Author: Buckaroo
Author URI: http://www.buckaroo.nl
Description: Buckaroo payment system plugin for WooCommerce.
Version: 3.1.1
Text Domain: wc-buckaroo-bpe-gateway
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

// Define BK_PLUGIN_FILE.
if (!defined('BK_PLUGIN_FILE')) {
    define('BK_PLUGIN_FILE', __FILE__);
}

require_once dirname(__FILE__). "/library/Buckaroo_Logger_Storage.php";

if(isset($_GET['buckaroo_download_log_file']) && is_string($_GET['buckaroo_download_log_file'])) {
    Buckaroo_Logger_Storage::downloadFile(sanitize_text_field($_GET['buckaroo_download_log_file']));
}

require_once dirname(__FILE__). "/library/Buckaroo_Logger.php";
require_once dirname(__FILE__). "/library/Buckaroo_Order_Fee.php";
require_once dirname(__FILE__). "/library/Buckaroo_Cron_Events.php";
require_once dirname(__FILE__). "/library/Buckaroo_Order_Details.php";
require_once dirname(__FILE__). "/library/Buckaroo_Disable_Gateways.php";
require_once dirname(__FILE__). "/install/class-wcb-install.php";
require_once dirname(__FILE__). "/install/migration/Buckaroo_Migration_Handler.php";
require_once dirname(__FILE__). "/Buckaroo_Load_Gateways.php";
require_once dirname(__FILE__). "/controllers/PaypalExpress.php";

/**
 * Remove gateways based on min/max value or idin verificaiton
 */
new Buckaroo_Disable_Gateways();
/**
 * Register additional fee hook
 */
new Buckaroo_Order_Fee();
/**
 * Start runing buckaroo events
 */
new Buckaroo_Cron_Events();
/**
 * Handle plugin updates
 */
new Buckaroo_Migration_Handler();
/**
 * Handles paypal express buttons when active
 */
new Buckaroo_Paypal_Express(
    new Buckaroo_Paypal_Express_Shipping(),
    new Buckaroo_Paypal_Express_Order(),
    new Buckaroo_Paypal_Express_Cart()
);

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

    if (class_exists('WC_Order') && is_checkout()) {
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
add_action('woocommerce_api_wc_push_buckaroo', 'buckaroo_push_class_init');

add_action( 'wp_ajax_order_capture', 'orderCapture' );
add_action( 'wp_ajax_buckaroo_test_credentials', 'buckaroo_test_credentials' );

function buckaroo_test_credentials()
{
    if (!isset($_POST['website_key']) || !strlen(trim($_POST['website_key']))) {
        wp_die(
            __('Credentials are incorrect',  'wc-buckaroo-bpe-gateway')
        );
    }

    if (!isset($_POST['secret_key']) || !strlen(trim($_POST['secret_key']))) {
        wp_die(
            __('Credentials are incorrect',  'wc-buckaroo-bpe-gateway')
        );
    }

    $url = 'https://testcheckout.buckaroo.nl/json/Transaction/Specification/ideal?serviceVersion=2';

    $timeStamp = time();
    $nonce = bin2hex(random_bytes(8));

    $website_key = sanitize_text_field($_POST['website_key']);
    $secret_key = sanitize_text_field($_POST['secret_key']);

    $body = implode(
        "",
        [
            $website_key,
            'GET',
            strtolower(
                rawurlencode(
                    str_replace('https://', '', $url)
                )
            ),
            $timeStamp,
            $nonce,
            ''
        ]
    );

    $hmacAuthorization =  "Authorization: hmac " . implode(
        ':',
        [
            $website_key,
            base64_encode(
                hash_hmac(
                    'sha256',
                    $body,
                    $secret_key,
                    true
                )
            ),
            $nonce,
            $timeStamp,
        ]
    );

    $response = wp_remote_get(
        $url,
        array(
            "headers" =>   $hmacAuthorization
        )
    );
    if ($response['response']['code'] === 200) {
        wp_die(
            __('Credentials are OK',  'wc-buckaroo-bpe-gateway')
        );
    } else {
        wp_die(
            __('Credentials are incorrect',  'wc-buckaroo-bpe-gateway')
        );
    }
}

include( plugin_dir_path(__FILE__) . 'includes/admin/meta-boxes/class-wc-meta-box-order-capture.php');



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
register_deactivation_hook(__FILE__, 'buckaroo_deactivation');

function buckaroo_deactivation()
{
    Buckaroo_Cron_Events::unschedule();
}

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
        'admin.php?page=wc-settings&tab=buckaroo_settings'
    );
    add_submenu_page(
        'admin.php?page=wc-settings&tab=buckaroo_settings',
        __('Payment methods',  'wc-buckaroo-bpe-gateway'),
        __('Payment methods',  'wc-buckaroo-bpe-gateway'),
        'manage_options',
        'admin.php?page=wc-settings&tab=buckaroo_settings&section=methods'
    );
    add_submenu_page(
        'admin.php?page=wc-settings&tab=buckaroo_settings',
        __('Report',  'wc-buckaroo-bpe-gateway'),
        __('Report',  'wc-buckaroo-bpe-gateway'),
        'manage_options',
        'admin.php?page=wc-settings&tab=buckaroo_settings&section=report'
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
    include_once __DIR__.'/templates/Buckaroo_Report_Page.php';
    include_once __DIR__.'/gateway-buckaroo-mastersettings.php';
    $settings[] = include_once plugin_dir_path(__FILE__). "WC_Buckaroo_Settings_Page.php";
    return $settings;
}

function buckaroo_init_gateway()
{
    //no code should be implemented before testing for active woocommerce
    if (!class_exists('WC_Order')) {
        set_transient(get_current_user_id().'buckaroo_require_woocommerce', true);
        return;
    }
    delete_transient( get_current_user_id().'buckaroo_require_woocommerce' );

    add_filter(
        'plugin_action_links_'.plugin_basename(__FILE__), 'buckaroo_add_setting_link'
    );
    add_filter(
        'woocommerce_get_settings_pages', 'buckaroo_add_woocommerce_settings_page'
    );
    add_action('admin_menu', 'buckaroo_page_menu');

    require_once 'library/include.php';
  

    load_plugin_textdomain('wc-buckaroo-bpe-gateway', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    
    $gateway_loader = new Buckaroo_Load_Gateways();
    $gateway_loader->load();

    add_filter('woocommerce_payment_gateways', [$gateway_loader, 'hook_gateways_to_woocommerce']);


    require_once __DIR__ . '/library/wp-actions/ApplePayButtons.php';
    (new ApplePayButtons)->loadActions();   
    

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

    //do a install if the plugin was installed prior to 2.24.1 
    //make sure we have all our plugin files loaded
    WC_Buckaroo_Install::installUntrackedInstalation();
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
    if (!empty($_GET['bck_err']) && ($error = base64_decode($_GET['bck_err']))) {
        wc_add_notice(__(sanitize_text_field($error), 'wc-buckaroo-bpe-gateway'), 'error');
    }
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
    if (!isset($_POST['order_id'])) {
        echo json_encode(
            [
                "errors" => [
                    "error_capture"=>[
                        [__('A valid order number is required')]
                    ]
                ]
            ]
        );
        exit;
    }

    $paymentMethod = get_post_meta( (int)sanitize_text_field($_POST['order_id']), '_wc_order_selected_payment_method', true);

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
            $gateway->process_capture()
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
        echo '<div class="notice notice-'.esc_attr($message['type']).' is-dismissible"><p>'.wp_kses($message['message'],array("b"=>array(),"p"=>array())).'</p></div>';
    }
    if(get_transient( get_current_user_id().'buckaroo_require_woocommerce') ) {
        delete_transient( get_current_user_id().'buckaroo_require_woocommerce' );
        echo '<div class="notice notice-error"><p>'.esc_html__(
            'Buckaroo BPE requires WooCommerce to be installed and active',  'wc-buckaroo-bpe-gateway'
        ).'</p></div>';
    }
}
add_action('admin_notices', 'buckaroo_admin_notice');

//test
