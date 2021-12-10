<?php

require_once(dirname(__FILE__) . '/../paymentmethod.php');

class BuckarooRequestToPay extends BuckarooPaymentMethod {
    public function __construct() {
        $this->type = "RequestToPay";
        $this->version = 1;
    }

    /**
     * @access public
     * @param array $customVars
     * @return callable parent::Pay()
     */
    public function Pay($customVars = array())
    {
        $this->setCustomVar(
            [
                'DebtorName'=>
                [
                    [
                        'value'=> $customVars['CustomerFirstName'] . ' ' . $customVars['CustomerLastName'],
                        'group'=>''
                    ]
                ]
            ]
        );
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

    /**
     * @param $data
     * @throws Exception
     */
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
