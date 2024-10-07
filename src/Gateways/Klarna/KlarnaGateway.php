<?php

namespace Buckaroo\Woocommerce\Gateways\Klarna;

use Buckaroo\Woocommerce\Components\OrderItem;
use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;
use DOMDocument;
use DOMXPath;

class KlarnaGateway extends AbstractPaymentGateway
{
    const PAYMENT_CLASS = KlarnaProcessor::class;
    protected $type;
    protected $vattype;
    protected $klarnaPaymentFlowId = '';

    public function __construct()
    {
        $this->has_fields = true;
        $this->type = 'klarna';
        $this->setIcon('24x24/klarna.svg', 'svg/klarna.svg');
        $this->setCountry();

        parent::__construct();
        $this->addRefundSupport();
    }

    /**
     * Can the order be refunded
     *
     * @param integer $order_id
     * @param integer $amount defaults to null
     * @param string $reason
     * @return callable|string function or error
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        return $this->processDefaultRefund($order_id, $amount, $reason);
    }

    /**
     * Validate payment fields on the frontend.
     *
     * @access public
     * @return void
     */
    public function validate_fields()
    {
        $gender = $this->request($this->getKlarnaSelector() . '-gender');

        if (!in_array($gender, array('male', 'female'))) {
            wc_add_notice(__('Unknown gender', 'wc-buckaroo-bpe-gateway'), 'error');
        }

        if ($this->request('ship_to_different_address') !== null) {
            $countryCode = $this->request('shipping_country') == 'NL' ? $this->request('shipping_country') : '';
            $countryCode = $this->request('billing_country') == 'NL' ? $this->request('billing_country') : $countryCode;
            if (!empty($countryCode)
                && strtolower($this->klarnaPaymentFlowId) !== 'pay') {

                return wc_add_notice(__('Payment method is not supported for country ' . '(' . esc_html($countryCode) . ')', 'wc-buckaroo-bpe-gateway'), 'error');
            }
        } elseif (
            ($this->request('billing_country') == 'NL')
            && strtolower($this->klarnaPaymentFlowId) !== 'pay'
        ) {

            return wc_add_notice(__('Payment method is not supported for country ' . '(' . esc_html($this->request('billing_country')) . ')', 'wc-buckaroo-bpe-gateway'), 'error');
        }
    }

    public function getKlarnaSelector()
    {
        return str_replace('_', '-', $this->id);
    }

    public function getKlarnaPaymentFlow()
    {
        return $this->klarnaPaymentFlowId;
    }

    public function get_product_data(OrderItem $order_item)
    {
        $product = parent::get_product_data($order_item);

        if ($order_item->get_type() === 'line_item') {

            $img = $this->getProductImage($order_item->get_order_item()->get_product());

            if (!empty($img)) {
                $product['imgUrl'] = $img;
            }

            $product['url'] = get_permalink($order_item->get_id());
        }
        return $product;
    }

    public function getProductImage($product)
    {
        $imgTag = $product->get_image();
        $doc = new DOMDocument();
        $doc->loadHTML($imgTag);
        $xpath = new DOMXPath($doc);
        $imageUrl = $xpath->evaluate('string(//img/@src)');

        return $imageUrl;
    }

    /** @inheritDoc */
    public function init_form_fields()
    {
        parent::init_form_fields();

        if ($this->id !== 'buckaroo_klarnapii') {
            $this->add_financial_warning_field();
        }
    }

    /**  @inheritDoc */
    protected function setProperties()
    {
        parent::setProperties();
        $this->vattype = $this->get_option('vattype');
    }
}