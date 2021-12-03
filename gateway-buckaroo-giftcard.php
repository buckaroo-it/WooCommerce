<?php

require_once dirname(__FILE__) . '/library/api/paymentmethods/giftcard/giftcard.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_Giftcard extends WC_Gateway_Buckaroo
{
    const PAYMENT_CLASS = BuckarooGiftCard::class;
    public $giftcards;

    public function __construct()
    {
        $this->id                     = 'buckaroo_giftcard';
        $this->title                  = 'Giftcards';
        $this->has_fields             = false;
        $this->method_title           = "Buckaroo Giftcards";
        $this->setIcon('24x24/giftcard.gif', 'new/Giftcards.png');

        parent::__construct();
        $this->addRefundSupport();
    }
    /**  @inheritDoc */
    protected function setProperties()
    {
        parent::setProperties();
        $this->giftcards = $this->get_option('giftcards');

    }

    /**
     * Can the order be refunded
     * @param object $order WC_Order
     * @return object & string
     */
    public function can_refund_order($order)
    {
        return $order && $order->get_transaction_id();
    }

    /**
     * Can the order be refunded
     * @param integer $order_id
     * @param integer $amount defaults to null
     * @param string $reason
     * @return callable|string function or error
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $order = wc_get_order($order_id);
        if (!$this->can_refund_order($order)) {
            return new WP_Error('error_refund_trid', __("Refund failed: Order not in ready state, Buckaroo transaction ID do not exists."));
        }
        update_post_meta($order_id, '_pushallowed', 'busy');
        $GLOBALS['plugin_id']             = $this->plugin_id . $this->id . '_settings';
        $order                            = wc_get_order($order_id);
        $giftcard                         = new BuckarooGiftCard();
        $giftcard->amountDedit            = 0;
        $giftcard->amountCredit           = $amount;
        $giftcard->currency               = $this->currency;
        $giftcard->description            = $reason;
        $giftcard->invoiceId              = $order->get_order_number();
        $giftcard->orderId                = $order_id;
        $giftcard->OriginalTransactionKey = $order->get_transaction_id();
        $giftcard->returnUrl              = $this->notify_url;
        $clean_order_no                   = (int) str_replace('#', '', $order->get_order_number());
        $giftcard->setType(get_post_meta($clean_order_no, '_payment_method_transaction', true));
        $giftcard->version = 1;
        $payment_type      = str_replace('buckaroo_', '', strtolower($this->id));
        $giftcard->channel = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $response          = null;

        $orderDataForChecking = $giftcard->getOrderRefundData();

        try {
            $giftcard->checkRefundData($orderDataForChecking);
            $response = $giftcard->Refund();
        } catch (exception $e) {
            update_post_meta($order_id, '_pushallowed', 'ok');
            return new WP_Error('refund_error', __($e->getMessage()));
        }
        return fn_buckaroo_process_refund($response, $order, $amount, $this->currency);
    }

    /**
     * Validate payment fields on the frontend.
     *
     * @return bool
     */
    public function validate_fields()
    {
        if (version_compare(WC()->version, '3.6', '<')) {
            resetOrder();
        }
        return;
    }

    /**
     * Process payment
     *
     * @param integer $order_id
     * @return callable fn_buckaroo_process_response()
     */
    public function process_payment($order_id)
    {
        $order = getWCOrder($order_id);
        /** @var BuckarooGiftCard */
        $giftcard = $this->createDebitRequest($order);

        $customVars            = array();

        $customVars['servicesSelectableByClient'] = $this->giftcards;

     

        $response = $giftcard->Pay($customVars);
        return fn_buckaroo_process_response($this, $response);
    }
    /**
     * Add fields to the form_fields() array, specific to this page.
     *
     * @access public
     */
    public function init_form_fields()
    {
        parent::init_form_fields();

        add_filter('woocommerce_settings_api_form_fields_' . $this->id, array($this, 'enqueue_script_certificate'));

        add_filter('woocommerce_settings_api_form_fields_' . $this->id, array($this, 'enqueue_script_hide_local'));

        //Start Dynamic Rendering of Hidden Fields
        $options      = get_option("woocommerce_" . $this->id . "_settings", null);
        $ccontent_arr = array();
        $keybase      = 'certificatecontents';
        $keycount     = 1;
        if (isset($_GET['section']) && $_GET['section'] == 'buckaroo_giftcard'): ?>
            <div class="notice notice-info">
                <p>
                    <?php echo __("<b>Important:</b> Group giftcards can only be refunded from the Buckaroo Plaza.", 'wc-buckaroo-bpe-gateway'); ?>
                </p>
            </div>
        <?php endif;
        if (!empty($options["$keybase$keycount"])) {
            while (!empty($options["$keybase$keycount"])) {
                $ccontent_arr[] = "$keybase$keycount";
                $keycount++;
            }
        }
        $while_key                 = 1;
        $selectcertificate_options = array('none' => 'None selected');

        while ($while_key != $keycount) {
            $this->form_fields["certificatecontents$while_key"] = array(
                'title'       => '',
                'type'        => 'hidden',
                'description' => '',
                'default'     => '',
            );
            $this->form_fields["certificateuploadtime$while_key"] = array(
                'title'       => '',
                'type'        => 'hidden',
                'description' => '',
                'default'     => '');
            $this->form_fields["certificatename$while_key"] = array(
                'title'       => '',
                'type'        => 'hidden',
                'description' => '',
                'default'     => '');
            $selectcertificate_options["$while_key"] = $options["certificatename$while_key"];

            $while_key++;
        }
        $final_ccontent                                          = $keycount;
        $this->form_fields["certificatecontents$final_ccontent"] = array(
            'title'       => '',
            'type'        => 'hidden',
            'description' => '',
            'default'     => '');
        $this->form_fields["certificateuploadtime$final_ccontent"] = array(
            'title'       => '',
            'type'        => 'hidden',
            'description' => '',
            'default'     => '');
        $this->form_fields["certificatename$final_ccontent"] = array(
            'title'       => '',
            'type'        => 'hidden',
            'description' => '',
            'default'     => '');

        $this->form_fields['selectcertificate'] = array(
            'title'       => __('Select Certificate', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'select',
            'description' => __('Select your certificate by name.', 'wc-buckaroo-bpe-gateway'),
            'options'     => $selectcertificate_options,
            'default'     => 'none',
        );
        $this->form_fields['choosecertificate'] = array(
            'title'       => __('', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'file',
            'description' => __(''),
            'default'     => '');

        $this->form_fields['giftcards'] = array(
            'title'       => __('List of authorized giftcards', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'text',
            'description' => __('Giftcards must be comma separated', 'wc-buckaroo-bpe-gateway'),
            'default'     => 'westlandbon,ideal,ippies,babygiftcard,babyparkgiftcard,beautywellness,boekenbon,boekenvoordeel,designshopsgiftcard,fashioncheque,fashionucadeaukaart,fijncadeau,koffiecadeau,kokenzo,kookcadeau,nationaleentertainmentcard,naturesgift,podiumcadeaukaart,shoesaccessories,webshopgiftcard,wijncadeau,wonenzo,yourgift,vvvgiftcard,parfumcadeaukaart');
    }

}
