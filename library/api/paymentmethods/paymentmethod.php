<?php
require_once(dirname(__FILE__) . '/../../logger.php');
require_once(dirname(__FILE__) . '/../abstract.php');
require_once(dirname(__FILE__) . '/../soap.php');
require_once(dirname(__FILE__) . '/responsefactory.php');

/**
* @package Buckaroo
*/
abstract class BuckarooPaymentMethod extends BuckarooAbstract {

    protected $type;

    /**
     * @param mixed $type
     */
    public function setType($type) {
        $this->type = $type;
    }

    /**
     * @return mixed
     */
    public function getType() {
        return $this->type;
    }

    public $currency;
    public $amountDedit;
    public $amountCredit = 0;
    public $orderId;
    public $invoiceId;
    public $description;
    public $OriginalTransactionKey;
    public $OriginalInvoiceNumber;
    public $AmountVat;
    public $returnUrl;
    public $mode;
    public $version;
    public $usecreditmanagment = 0;
    public $usenotification = 0;
    public $sellerprotection = 0;
    public $CreditCardDataEncrypted;
    protected $data = array();

    public $CustomerCardName;

    /**
     * Populate generic fields in $customVars() array 
     * 
     * @access public
     * @param array $customeVars defaults to empty array
     * @return callable $this->PayGlobal()
     */
    public function Pay($customVars = Array()) {
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

        return $this->PayGlobal();
    }

    /**
     * Populate generic fields in $customVars() array 
     * 
     * @access public
     * @param array $customeVars defaults to empty array
     * @return callable $this->PayGlobal()
     */
    public function Authorize($customVars = Array()) {
        $this->data['services'][$this->type]['action'] = 'Authorize';
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

        return $this->PayGlobal();
    }    


    /**
     * Populate generic fields for a refund 
     * 
     * @access public
     * @return callable $this->RefundGlobal()
     */
    public function Refund() {
        $this->data['services'][$this->type]['action'] = 'Refund';
        $this->data['services'][$this->type]['version'] = $this->version;

        return $this->RefundGlobal();
    }

    /**
     * Populate generic fields for a refund
     *
     * @access public
     * @return callable $this->RefundGlobal()
     */
    public function guaranteeRefund() {
        $this->data['services'][$this->type]['action'] = 'CreditNote';
        $this->data['services'][$this->type]['version'] = $this->version;

        $this->data['OriginalInvoiceNumber'] = $this->OriginalInvoiceNumber;
        $this->data['AmountVat'] = $this->AmountVat;

        return $this->RefundGlobal();
    }

    /**
     * Build soap request for payment and get response
     * 
     * @access public
     * @return callable BuckarooResponseFactory::getResponse($soap->transactionRequest())
     */
    public function PayGlobal() {
        $this->data['currency'] = $this->currency;
        $this->data['amountDebit'] = $this->amountDedit;
        $this->data['amountCredit'] = $this->amountCredit;
        $this->data['invoice'] = $this->invoiceId;
        $this->data['order'] = $this->orderId;
        $this->data['description'] = $this->description;
        $this->data['returnUrl'] = $this->returnUrl;
        $this->data['mode'] = $this->mode;
        $this->data['channel'] = $this->channel;
        $this->data['CustomerCardName'] = $this->customercardname;

        $soap = new BuckarooSoap($this->data);
        return BuckarooResponseFactory::getResponse($soap->transactionRequest());
    }

    /**
     * Build soap request for payment and get response
     * 
     * @access public
     * @return callable BuckarooResponseFactory::getResponse($soap->transactionRequest())
     */
    public function CaptureGlobal() {
        $this->data['currency'] = $this->currency;
        $this->data['amountDebit'] = $this->amountDedit;
        $this->data['amountCredit'] = $this->amountCredit;
        $this->data['invoice'] = $this->invoiceId;
        $this->data['OriginalTransactionKey'] = $this->OriginalTransactionKey;
        $this->data['order'] = $this->orderId;
        $this->data['description'] = $this->description;
        $this->data['returnUrl'] = $this->returnUrl;
        $this->data['mode'] = $this->mode;
        $this->data['channel'] = $this->channel;
        $soap = new BuckarooSoap($this->data);
        return BuckarooResponseFactory::getResponse($soap->transactionRequest());
    }

    /**
     * Build soap request for refund and get response
     * 
     * @access public
     * @return callable BuckarooResponseFactory::getResponse($soap->transactionRequest())
     */
    public function RefundGlobal() {

//        $orderRefundData = $this->getOrderRefundData();
//
//        try{
//            $this->checkRefundData($orderRefundData);
//        } catch(Exception $e) {
//
//        }

        $this->data['currency'] = $this->currency;
        $this->data['amountDebit'] = $this->amountDedit;
        $this->data['amountCredit'] = $this->amountCredit;
        $this->data['invoice'] = $this->invoiceId . '-R';
        $this->data['order'] = $this->orderId;
        $this->data['description'] = $this->description;
        $this->data['OriginalTransactionKey'] = $this->OriginalTransactionKey;
        $this->data['returnUrl'] = $this->returnUrl;
        $this->data['mode'] = $this->mode;
        $this->data['channel'] = $this->channel;
        $soap = new BuckarooSoap($this->data);
        return BuckarooResponseFactory::getResponse($soap->transactionRequest());
    }

    /**
     * Calculate checksum from iban and confirm validity of iban
     * 
     * @access public
     * @param string $iban
     * @return boolean
     */
    public static function isIBAN($iban) {
        // Normalize input (remove spaces and make upcase)
        $iban = strtoupper(str_replace(' ', '', $iban));

        if (preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{1,30}$/', $iban)) {
            $country = substr($iban, 0, 2);
            $check = intval(substr($iban, 2, 2));
            $account = substr($iban, 4);

            // To numeric representation
            $search = range('A', 'Z');
            foreach (range(10, 35) as $tmp) {
                $replace[] = strval($tmp);
            }
            $numstr = str_replace($search, $replace, $account . $country . '00');

            // Calculate checksum
            $checksum = intval(substr($numstr, 0, 1));
            for ($pos = 1; $pos < strlen($numstr); $pos++) {
                $checksum *= 10;
                $checksum += intval(substr($numstr, $pos, 1));
                $checksum %= 97;
            }

            return ((98 - $checksum) == $check);
        } else {
            return false;
        }
    }

    /**
     * @param $data
     * @throws Exception
     */
    public function checkRefundData($data){
        //Check if order is refundable

        foreach ($data as $itemKey) {
            if (empty($itemKey['total']) && !empty($itemKey['tax'])) {
                throw new Exception( 'Tax only cannot be refund' );
            }
        }

        $order = wc_get_order( $this->orderId );
        $items = $order->get_items();
        $feeItems = $order->get_items('fee');

        $feeCost = $order->get_total_fees();

        $orderFeeRefund = $order->get_item_count_refunded('fee');

        $shippingCostWithoutTax = (float) $order->get_shipping_total();
        $shippingTax = (float)$order->get_shipping_tax();
        $shippingCosts = round($shippingCostWithoutTax + $shippingTax, 2);

        $shippingRefundedCosts = $order->get_total_shipping_refunded();

        $shippingMethods = $order->get_items('shipping');

        foreach ($items as $item_id => $item_data) {

            if ($items[$item_id] instanceof WC_Order_Item_Product && isset($data[$item_id])) {

                $itemPrice = 0;

                $itemTotal = $items[$item_id]->get_total();
                $itemQuantity = $items[$item_id]->get_quantity();
                $itemPrice = $itemTotal / $itemQuantity;

                $tax = $items[$item_id]->get_taxes();
                $taxId = 3;

                if (!empty($tax['total'])) {
                    foreach ($tax['total'] as $key => $value) {
                        $taxId = $key;
                    }
                }

                $itemTax = $items[$item_id]->get_total_tax();

                $itemRefundedTax = $order->get_tax_refunded_for_item($item_id, $taxId);

                if ( empty($data[$item_id]['qty']) ) {
                    throw new Exception('Product quantity doesn`t choose');
                }

                if ((float)$itemPrice * $data[$item_id]['qty'] !== (float)$data[$item_id]['total']) {
                    throw new Exception('Incorrect entered product price. Please check refund product price and tax amounts');
                }

                if (!empty($data[$item_id]['qty'])) {
//                    $itemQuantity = $item_data->get_quantity();
                    $item_refunded = $order->get_qty_refunded_for_item($item_id);
                    if ($itemQuantity === abs($item_refunded) - $data[$item_id]['qty']) {
                        throw new Exception('Product already refunded');
                    } elseif ($itemQuantity < abs($item_refunded) ) {
                        $availableRefundQty = $itemQuantity - (abs($item_refunded) - $data[$item_id]['qty']);
                        $message = $availableRefundQty . ' item(s) can be refund';
                        throw new Exception( $message );
                    }
                }

                if ($itemRefundedTax > $itemTax) {
                    throw new Exception('Incorrect refund tax price');
                }
            }
        }

        foreach ($feeItems as $item_id => $item_data) {
            if ($orderFeeRefund > 1) {
                throw new Exception('Payment fee already refunded');
            }
            if (!empty($data[$item_id]['total'])) {
                $totalFeePrice = round($data[$item_id]['total']+$data[$item_id]['tax'],2);
                if ($totalFeePrice > $feeCost) {
                    throw new Exception('Enter valid payment fee:' . $feeCost . esc_attr(get_woocommerce_currency()) );
                } elseif($totalFeePrice < $feeCost) {
                    $balance = $feeCost - $totalFeePrice;
                    throw new Exception('Please add ' . $balance . ' ' . esc_attr(get_woocommerce_currency()) . ' to full refund payment fee cost' );
                }
            }
        }

        if ((float)$shippingCosts !== (float)$shippingRefundedCosts && !empty($shippingRefundedCosts)) {
            throw new Exception('Incorrect refund shipping price. Please check refund shipping price and tax amounts');
        }
    }

    /**
     *
     */
    public function getOrderRefundData( $line_item_totals = null, $line_item_tax_totals = null, $line_item_qtys = null ){

        $orderRefundData = [];

        if ($line_item_qtys === null) {
            $line_item_qtys = json_decode(stripslashes($_POST['line_item_qtys']), true);
        }

        if ($line_item_totals === null) {
            $line_item_totals = json_decode(stripslashes($_POST['line_item_totals']), true);
        }

        if ($line_item_tax_totals === null) {
            $line_item_tax_totals = json_decode(stripslashes($_POST['line_item_tax_totals']), true);
        }

        foreach ($line_item_totals as $key => $value) {
            if (!empty($value)) {
                $orderRefundData[$key]['total'] = $value;
            }
        }

        foreach ($line_item_tax_totals as $key => $keyItem) {
            foreach ($keyItem as $taxItem => $taxItemValue) {
                if (!empty($taxItemValue)) {
                    $orderRefundData[$key]['tax'] = $taxItemValue;
                }
            }
        }
        if (!empty($line_item_qtys)){
            foreach ($line_item_qtys as $key => $value) {
                $orderRefundData[$key]['qty'] = $value;
            }
        }

        $orderRefundData['totalRefund'] = 0;
        foreach ($orderRefundData as $key => $item) {
            $orderRefundData['totalRefund'] += $orderRefundData[$key]['total'] + $orderRefundData[$key]['tax'];
        }

        return $orderRefundData;
    }
}
