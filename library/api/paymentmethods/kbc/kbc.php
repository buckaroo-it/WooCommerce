<?php

require_once(dirname(__FILE__) . '/../paymentmethod.php');

class BuckarooKBC extends BuckarooPaymentMethod {
    public function __construct() {
        $this->type = "KBCPaymentButton";
        $this->version = 1;
        $this->mode = BuckarooConfig::getMode($this->type);
    }

    /**
     * @access public
     * @param array $customVars
     * @return callable parent::Pay()
     */
    public function Pay($customVars = Array()){
        $this->data['services'][$this->type]['action'] = 'Pay';
        $this->data['services'][$this->type]['version'] = $this->version;

        if ($this->usenotification && !empty($customVars['Customeremail'])) {
            $this->data['services']['notification']['action'] = 'ExtraInfo';
            $this->data['services']['notification']['version'] = '1';
            $this->data['customVars']['notification']['NotificationType'] = $customVars['Notificationtype'];
            $this->data['customVars']['notification']['CommunicationMethod'] = 'email';
            $this->data['customVars']['notification']['RecipientEmail'] = $customVars['Customeremail'];
            $this->data['customVars']['notification']['RecipientFirstName'] = $customVars['CustomerFirstName'];
            $this->data['customVars']['notification']['RecipientLastName'] = $customVars['CustomerLastName'];
            $this->data['customVars']['notification']['RecipientGender'] = $customVars['Customergender'];

            if (!empty($customVars['Notificationdelay'])) {
                $this->data['customVars']['notification']['SendDatetime'] = $customVars['Notificationdelay'];
            }
        }

        return parent::Pay();
    }

    /**
     * @access public
     * @return callable parent::Refund();
     * @throws Exception
     */
    public function Refund() {
        return parent::Refund();
    }

    public function checkRefundData($data){

        //Check if order is refundable
        $order = wc_get_order( $this->orderId );
        $items = $order->get_items();
        $feeItems = $order->get_items('fee');

        $feeCost = $order->get_total_fees();

        $productRefund = $order->get_item_count_refunded();
        $orderFeeRefund = $order->get_item_count_refunded('fee');

        $shippingCosts = round(floatval($order->get_shipping_total()) + floatval($order->get_shipping_tax()), 2);

        $shippingRefundedCosts = $order->get_total_shipping_refunded();

        foreach ($items as $item_id => $item_data) {

            if ($items[$item_id] instanceof WC_Order_Item_Product) {
                if ($productRefund === 0 && (!empty($data[$item_id]['total']) || !empty($data[$item_id]['tax']))) {
                    throw new Exception('Product quantity doesn`t choose');
                }
                if (!empty($data[$item_id]['qty'])) {
                    $itemQuantity = $item_data->get_quantity();
                    $item_refunded = $order->get_qty_refunded_for_item($item_id);
                    if ($itemQuantity === abs($item_refunded) - $data[$item_id]['qty']) {
                        throw new Exception('Product already refunded');
                    } elseif ($itemQuantity < abs($item_refunded) ) {
                        $availableRefundQty = $itemQuantity - (abs($item_refunded) - $data[$item_id]['qty']);
                        $message = $availableRefundQty . ' item(s) can be refund';
                        throw new Exception( $message );
                    }
                }
            }
        }

        foreach ($feeItems as $item_id => $item_data) {
            if ($orderFeeRefund > 1) {
                throw new Exception('Payment fee already refunded');
            }
            if (!empty($data[$item_id]['total'])) {
                if (round($data[$item_id]['total']+$data[$item_id]['tax'],2) > $feeCost) {
                    throw new Exception('Enter valid payment fee:' . $feeCost . esc_attr(get_woocommerce_currency()) );
                } elseif(round($data[$item_id]['total']+$data[$item_id]['tax']) < $feeCost) {
                    $balance = $feeCost - round($data[$item_id]['total']+$data[$item_id]['tax']);
                    throw new Exception('Add ' . $balance . ' ' . esc_attr(get_woocommerce_currency()) . ' to payment fee cost' );
                }
            }
        }

        if ($shippingCosts < $shippingRefundedCosts) {
            throw new Exception('Incorrect refund shipping price');
        }
    }
}
