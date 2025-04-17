<?php

namespace Buckaroo\Woocommerce\Gateways\CreditCard;

use Buckaroo\Woocommerce\Core\Plugin;
use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;
use Buckaroo\Woocommerce\Gateways\AbstractProcessor;
use Buckaroo\Woocommerce\Services\Helper;
use WC_Order;

class CreditCardGateway extends AbstractPaymentGateway {
    const PAYMENT_CLASS                 = CreditCardProcessor::class;
    const REFUND_CLASS                  = CreditCardRefundProcessor::class;
    public const SHOW_IN_CHECKOUT_FIELD = 'show_in_checkout';
    public $creditCardProvider          = array();

    protected $creditcardmethod;

    protected $creditcardpayauthorize;
    public bool $capturable = true;

    protected array $supportedCurrencies = array(
        'ARS',
        'AUD',
        'BRL',
        'CAD',
        'CHF',
        'CNY',
        'CZK',
        'DKK',
        'EUR',
        'GBP',
        'HRK',
        'ISK',
        'JPY',
        'LTL',
        'LVL',
        'MXN',
        'NOK',
        'NZD',
        'PLN',
        'RUB',
        'SEK',
        'TRY',
        'USD',
        'ZAR',
    );
    public static array $cards           = array(
        'amex_creditcard'       => array( 'gateway_class' => Cards\AmexGateway::class ),
        'maestro_creditcard'    => array( 'gateway_class' => Cards\MaestroGateway::class ),
        'mastercard_creditcard' => array( 'gateway_class' => Cards\MastercardGateway::class ),
        'visa_creditcard'       => array( 'gateway_class' => Cards\VisaGateway::class ),
    );

    public function __construct() {
        $this->setParameters();
        $this->setCreditcardIcon();
        $this->has_fields = true;

        parent::__construct();

        $this->addRefundSupport();
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

        if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
            $this->registerControllers();
        }
    }

    private function registerControllers() {
        $namespace = 'woocommerce_api_wc_gateway_buckaroo_creditcard';

        add_action( "{$namespace}-hosted-fields-token", array( HostedFieldsController::class, 'getToken' ) );
    }

    /**
     * Validate fields
     *
     * @return void;
     */
    public function validate_fields() {
        parent::validate_fields();

        if ( $this->get_option( 'creditcardmethod' ) == 'encrypt' && $this->isSecure() ) {
            $encryptedData = $this->request->input( $this->id . '-encrypted-data' );

            if ( empty( $encryptedData ) || $encryptedData === null ) {
                wc_add_notice( __( 'Please complete the card form before proceeding.', 'wc-buckaroo-bpe-gateway' ), 'error' );
            }
        }
    }


    /**
     * Set gateway parameters
     *
     * @return void
     */
    public function setParameters() {
        $this->id           = 'buckaroo_creditcard';
        $this->title        = 'Credit and debit card';
        $this->method_title = 'Buckaroo Credit and debit card';
    }

    /**
     * Set credicard icon
     *
     * @return void
     */
    public function setCreditcardIcon() {
        $this->setIcon( 'svg/creditcards.svg' );
    }

    /**
     * Returns true if secure (https), false if not (http)
     */
    public function isSecure() {
        return ( ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' )
            || ! empty( $_SERVER['SERVER_PORT'] ) && $_SERVER['SERVER_PORT'] == 443;
    }

    /**
     * Process payment
     *
     * @param integer $order_id
     * @return callable fn_buckaroo_process_response()
     */
    public function process_payment( $order_id ) {
        $processedPayment = parent::process_payment( $order_id );

        if ( $processedPayment['result'] == 'success' && $this->creditcardpayauthorize == 'authorize' ) {
            update_post_meta( $order_id, '_wc_order_authorized', 'yes' );
            $this->set_order_capture( $order_id, 'Creditcard', $this->request->input( $this->id . '-creditcard-issuer' ) );
        }
        return $processedPayment;
    }

    public function getCardsList() {
        $cards     = array();
        $cardsDesc = array(
            'amex'       => 'American Express',
            'maestro'    => 'Maestro',
            'mastercard' => 'Mastercard',
            'visa'       => 'Visa',
        );
        if ( is_array( $this->creditCardProvider ) ) {
            foreach ( $this->creditCardProvider as $value ) {
                $cards[] = array(
                    'servicename' => $value,
                    'displayname' => $cardsDesc[ $value ],
                );
            }
        }
        return $cards;
    }

    /**
     * Add fields to the form_fields() array, specific to this page.
     *
     * @access public
     */
    public function init_form_fields() {
        parent::init_form_fields();

        $this->form_fields['creditcardmethod'] = array(
            'title'       => __( 'Credit and debit card method', 'wc-buckaroo-bpe-gateway' ),
            'type'        => 'select',
            'description' => __( 'Redirect user to Buckaroo or enter credit or debit card information (directly) inline in the checkout. SSL is required to enable inline credit or debit card information.', 'wc-buckaroo-bpe-gateway' ),
            'options'     => array(
                'redirect' => 'Redirect',
                'encrypt'  => 'Inline (Hosted Fields)',
            ),
            'default'     => 'redirect',
            'desc_tip'    => __( 'Check with Buckaroo whether Client Side Encryption is enabled, otherwise transactions will fail. If in doubt, please contact us.', 'wc-buckaroo-bpe-gateway' ),

        );
        $this->form_fields['creditcardpayauthorize']       = array(
            'title'       => __( 'Credit and debit card flow', 'wc-buckaroo-bpe-gateway' ),
            'type'        => 'select',
            'description' => __( 'Choose to execute Pay or Capture call', 'wc-buckaroo-bpe-gateway' ),
            'options'     => array(
                'pay'       => 'Pay',
                'authorize' => 'Authorize',
            ),
            'default'     => 'pay',
        );
        $this->form_fields['hosted_fields_client_id']      = array(
            'title'       => __( 'Buckaroo Hosted Fields Client ID', 'wc-buckaroo-bpe-gateway' ),
            'type'        => 'password',
            'description' => __( 'Enter your Buckaroo Hosted Fields Client ID, obtainable from the Buckaroo Plaza at -> Settings -> Token registration.', 'wc-buckaroo-bpe-gateway' ),
        );
        $this->form_fields['hosted_fields_client_secret']  = array(
            'title'       => __( 'Buckaroo Hosted Fields Client Secret', 'wc-buckaroo-bpe-gateway' ),
            'type'        => 'password',
            'description' => __( 'Enter your Buckaroo Hosted Fields Client Secret, obtainable from the Buckaroo Plaza at -> Settings -> Token registration.', 'wc-buckaroo-bpe-gateway' ),
        );
        $this->form_fields['AllowedProvider']              = array(
            'title'       => __( 'Allowed provider', 'wc-buckaroo-bpe-gateway' ),
            'type'        => 'multiselect',
            'options'     => array(
                'amex'       => 'American Express',
                'maestro'    => 'Maestro',
                'mastercard' => 'Mastercard',
                'visa'       => 'Visa',
            ),
            'description' => __( 'Select which credit or debit card providers will be visible to customer', 'wc-buckaroo-bpe-gateway' ),
            'default'     => array(
                'amex',
                'mastercard',
                'maestro',
                'visa',
            ),
        );
        $this->form_fields[ self::SHOW_IN_CHECKOUT_FIELD ] = array(
            'title'       => __( 'Show separate in checkout', 'wc-buckaroo-bpe-gateway' ),
            'type'        => 'multiselect',
            'options'     => array(
                ''           => __( 'None', 'wc-buckaroo-bpe-gateway' ),
                'amex'       => 'American Express',
                'mastercard' => 'Mastercard',
                'maestro'    => 'Maestro',
                'visa'       => 'Visa',
            ),
            'description' => __( 'Select which credit or debit card providers will be shown separately in the checkout', 'wc-buckaroo-bpe-gateway' ),
            'default'     => array(),
        );
    }

    public function enqueue_scripts() {
        if ( class_exists( 'WC_Order' ) && is_checkout() ) {
            wp_enqueue_script(
                'buckaroo_hosted_fields',
                'https://hostedfields-externalapi.prod-pci.buckaroo.io/v1/sdk',
                array(),
                Plugin::VERSION,
                true
            );
        }
    }

    /** @inheritDoc */
    public function process_admin_options() {
        parent::process_admin_options();
        $this->after_admin_options_update();
    }

    /**
     * Do code after admin options update
     *
     * @return void
     */
    public function after_admin_options_update() {
        set_transient( 'buckaroo_credicard_updated', true );
    }

    /**
     * Save only creditcards that are allowed
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return mixed
     */
    public function validate_show_in_checkout_field( $key, $value ) {
        $allowed = $this->settings['AllowedProvider'];
        if ( is_array( $value ) ) {
            return array_filter(
                $value,
                function ( $provider ) use ( $allowed ) {
                    return in_array( $provider, $allowed );
                }
            );
        }
        return $value;
    }

    /**  @inheritDoc */
    protected function setProperties() {
        parent::setProperties();
        $this->creditCardProvider     = $this->get_option( 'AllowedProvider', array() );
        $this->creditcardmethod       = $this->get_option( 'creditcardmethod', 'redirect' );
        $this->creditcardpayauthorize = $this->get_option( 'creditcardpayauthorize', 'Pay' );
    }

    public function canShowCaptureForm( $order ): bool {
        $order = Helper::resolveOrder( $order );

        if ( ! $order instanceof WC_Order ) {
            return false;
        }

        return $this->creditcardpayauthorize == 'authorize' && get_post_meta( $order->get_id(), '_wc_order_authorized', true ) == 'yes';
    }

    public function getServiceCode( ?AbstractProcessor $processor = null ) {
        if ( $this->creditcardmethod == 'redirect' ) {
            return 'noservice';
        }

        return 'creditcard';
    }
}
