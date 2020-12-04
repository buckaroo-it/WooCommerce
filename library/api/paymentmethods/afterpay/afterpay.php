<?php
require_once(dirname(__FILE__).'/../paymentmethod.php');

/**
 * @package Buckaroo
 */
class BuckarooAfterPay extends BuckarooPaymentMethod {
    public $BillingGender;
    public $BillingInitials;
    public $BillingLastName;
    public $BillingBirthDate;
    public $BillingStreet;
    public $BillingHouseNumber;
    public $BillingHouseNumberSuffix;
    public $BillingPostalCode;
    public $BillingCity;
    public $BillingCountry;
    public $BillingEmail;
    public $BillingPhoneNumber;
    public $BillingLanguage;
    public $AddressesDiffer;
    public $ShippingGender;
    public $ShippingInitials;
    public $ShippingLastName;
    public $ShippingBirthDate;
    public $ShippingStreet;
    public $ShippingHouseNumber;
    public $ShippingHouseNumberSuffix;
    public $ShippingPostalCode;
    public $ShippingCity;
    public $ShippingCountryCode;
    public $ShippingEmail;
    public $ShippingPhoneNumber;
    public $ShippingLanguage;
    public $ShippingCosts;
    public $CustomerAccountNumber;
    public $CustomerIPAddress;
    public $Accept;

    public $B2B;
    public $CompanyCOCRegistration;
    public $CompanyName;
    public $CostCentre;
    public $VatNumber;

    /**
     * @access public
     * @param string $type
     */
    public function __construct($type = 'afterpaydigiaccept') {
        $this->type = $type;
        $this->version = '1';
        $this->mode = BuckarooConfig::getMode('AFTERPAY');
    }

    /**
     * @access public
     * @param array $customVars
     * @return void
     */
    public function Pay($customVars = Array()) {
        return null;
    }
    
    /**
     * @access public
     * @param array $products
     * @return callable parent::Pay();
     */
    public function PayOrAuthorizeAfterpay($products = Array(), $action) {
        $this->data['customVars'][$this->type]['BillingGender'] = $this->BillingGender;
        $this->data['customVars'][$this->type]['BillingInitials'] = $this->BillingInitials;
        $this->data['customVars'][$this->type]['BillingLastName'] = $this->BillingLastName;
        $this->data['customVars'][$this->type]['BillingBirthDate'] = $this->BillingBirthDate;
        $this->data['customVars'][$this->type]['BillingStreet'] = $this->BillingStreet;
        $this->data['customVars'][$this->type]['BillingHouseNumber'] = isset($this->BillingHouseNumber) ? $this->BillingHouseNumber . ' ' : $this->BillingHouseNumber;
        $this->data['customVars'][$this->type]['BillingHouseNumberSuffix'] = $this->BillingHouseNumberSuffix;
        $this->data['customVars'][$this->type]['BillingPostalCode'] = $this->BillingPostalCode;
        $this->data['customVars'][$this->type]['BillingCity'] = $this->BillingCity;
        $this->data['customVars'][$this->type]['BillingCountry'] = $this->BillingCountry;
        $this->data['customVars'][$this->type]['BillingEmail'] = $this->BillingEmail;
        $this->data['customVars'][$this->type]['BillingPhoneNumber'] = $this->BillingPhoneNumber;
        $this->data['customVars'][$this->type]['BillingLanguage'] = $this->BillingLanguage;
        $this->data['customVars'][$this->type]['AddressesDiffer'] = $this->AddressesDiffer;
        if ($this->AddressesDiffer == 'TRUE') {
            $this->data['customVars'][$this->type]['ShippingGender'] = $this->ShippingGender;
            $this->data['customVars'][$this->type]['ShippingInitials'] = $this->ShippingInitials;
            $this->data['customVars'][$this->type]['ShippingLastName'] = $this->ShippingLastName;
            $this->data['customVars'][$this->type]['ShippingBirthDate'] = $this->ShippingBirthDate;
            $this->data['customVars'][$this->type]['ShippingStreet'] = $this->ShippingStreet;
            $this->data['customVars'][$this->type]['ShippingHouseNumber'] = isset($this->ShippingHouseNumber) ? $this->ShippingHouseNumber . ' ' : $this->ShippingHouseNumber;
            $this->data['customVars'][$this->type]['ShippingHouseNumberSuffix'] = $this->ShippingHouseNumberSuffix;
            $this->data['customVars'][$this->type]['ShippingPostalCode'] = $this->ShippingPostalCode;
            $this->data['customVars'][$this->type]['ShippingCity'] = $this->ShippingCity;
            $this->data['customVars'][$this->type]['ShippingCountryCode'] = $this->ShippingCountryCode;
            $this->data['customVars'][$this->type]['ShippingEmail'] = $this->ShippingEmail;
            $this->data['customVars'][$this->type]['ShippingPhoneNumber'] = $this->ShippingPhoneNumber;
            $this->data['customVars'][$this->type]['ShippingLanguage'] = $this->ShippingLanguage;
        }
        if ($this->B2B == 'TRUE') {
            $this->data['customVars'][$this->type]['B2B'] = $this->B2B;
            $this->data['customVars'][$this->type]['CompanyCOCRegistration'] = $this->CompanyCOCRegistration;
            $this->data['customVars'][$this->type]['CompanyName'] = $this->CompanyName;
            $this->data['customVars'][$this->type]['CostCentre'] = $this->CostCentre;
            $this->data['customVars'][$this->type]['VatNumber'] = $this->VatNumber;
        }
        $this->data['customVars'][$this->type]['ShippingLanguage'] = $this->ShippingLanguage;
        if ($this->type == 'afterpayacceptgiro') {
            $this->data['customVars'][$this->type]['CustomerAccountNumber'] = $this->CustomerAccountNumber;
        }
        if ($this->ShippingCosts > 0) {
            $this->data['customVars'][$this->type]['ShippingCosts'] = $this->ShippingCosts;
        }
        $this->data['customVars'][$this->type]['CustomerIPAddress'] = $this->CustomerIPAddress;
        $this->data['customVars'][$this->type]['Accept'] = $this->Accept;
        $i = 1;

        // Merge products with same SKU 

        $mergedProducts = array();
        foreach ($products as $product) {
            if (! isset($mergedProducts[$product['ArticleId']])) {
                $mergedProducts[$product['ArticleId']] = $product;
            } else {
                $mergedProducts[$product['ArticleId']]["ArticleQuantity"] += 1;
            }
        }     
        
        $products = $mergedProducts;

        foreach($products as $p) {
            $this->data['customVars'][$this->type]["ArticleDescription"][$i - 1]["value"] = $p["ArticleDescription"];
            $this->data['customVars'][$this->type]["ArticleDescription"][$i - 1]["group"] = $i;
            $this->data['customVars'][$this->type]["ArticleId"][$i - 1]["value"] = $p["ArticleId"];
            $this->data['customVars'][$this->type]["ArticleId"][$i - 1]["group"] = $i;
            $this->data['customVars'][$this->type]["ArticleQuantity"][$i - 1]["value"] = $p["ArticleQuantity"];
            $this->data['customVars'][$this->type]["ArticleQuantity"][$i - 1]["group"] = $i;
            $this->data['customVars'][$this->type]["ArticleUnitprice"][$i - 1]["value"] = $p["ArticleUnitprice"];
            $this->data['customVars'][$this->type]["ArticleUnitprice"][$i - 1]["group"] = $i;
            $this->data['customVars'][$this->type]["ArticleVatcategory"][$i - 1]["value"] = $p["ArticleVatcategory"];
            $this->data['customVars'][$this->type]["ArticleVatcategory"][$i - 1]["group"] = $i;
            $i++;
        }

        $this->data['customVars'][$this->type]['ShippingGender'] = $this->ShippingGender;
        $this->data['customVars'][$this->type]['ShippingInitials'] = $this->ShippingInitials ?? $this->BillingInitials;
        $this->data['customVars'][$this->type]['ShippingLastName'] = $this->ShippingLastName ?? $this->BillingLastName;
        $this->data['customVars'][$this->type]['ShippingBirthDate'] = $this->ShippingBirthDate;
        $this->data['customVars'][$this->type]['ShippingStreet'] = $this->ShippingStreet;
        $this->data['customVars'][$this->type]['ShippingHouseNumber'] = isset($this->ShippingHouseNumber) ? $this->ShippingHouseNumber . ' ' : $this->ShippingHouseNumber;
        $this->data['customVars'][$this->type]['ShippingHouseNumberSuffix'] = $this->ShippingHouseNumberSuffix;
        $this->data['customVars'][$this->type]['ShippingPostalCode'] = $this->ShippingPostalCode;
        $this->data['customVars'][$this->type]['ShippingCity'] = $this->ShippingCity;
        $this->data['customVars'][$this->type]['ShippingCountryCode'] = $this->ShippingCountryCode;
        $this->data['customVars'][$this->type]['ShippingEmail'] = $this->ShippingEmail;
        $this->data['customVars'][$this->type]['ShippingPhoneNumber'] = $this->ShippingPhoneNumber;
        $this->data['customVars'][$this->type]['ShippingLanguage'] = $this->ShippingLanguage;

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

        return parent::$action();
    }

    /**
     * Populate generic fields for a refund 
     * 
     * @access public
     * * @param array $products
     * @return callable $this->RefundGlobal()
     */
    public function AfterPayRefund($products, $issuer) {

        $this->type = $issuer;
        $this->version = 1;
        $this->mode = BuckarooConfig::getMode($this->type);        

        $this->data['services'][$this->type]['action'] = 'Refund';
        $this->data['services'][$this->type]['version'] = $this->version;

        // Refunds have to be done on the captures (if authorize/capture is enabled)



        $i = 1;
        foreach($products as $p) {
            $this->data['customVars'][$this->type]["ArticleDescription"][$i - 1]["value"] = $p["ArticleDescription"];
            $this->data['customVars'][$this->type]["ArticleDescription"][$i - 1]["group"] = 'Article';
            $this->data['customVars'][$this->type]["ArticleId"][$i - 1]["value"] = $p["ArticleId"];
            $this->data['customVars'][$this->type]["ArticleId"][$i - 1]["group"] = 'Article';
            $this->data['customVars'][$this->type]["ArticleQuantity"][$i - 1]["value"] = $p["ArticleQuantity"];
            $this->data['customVars'][$this->type]["ArticleQuantity"][$i - 1]["group"] = 'Article';
            $this->data['customVars'][$this->type]["ArticleUnitprice"][$i - 1]["value"] = $p["ArticleUnitprice"];
            $this->data['customVars'][$this->type]["ArticleUnitprice"][$i - 1]["group"] = 'Article';
            $this->data['customVars'][$this->type]["ArticleVatcategory"][$i - 1]["value"] = $p["ArticleVatcategory"];
            $this->data['customVars'][$this->type]["ArticleVatcategory"][$i - 1]["group"] = 'Article';
            $i++;
        }  

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

        return $this->RefundGlobal();
    }

    /**
     * @access public
     * @param array $customVars
     * @param array $products
     * @return callable parent::PayGlobal()
     */
    public function Capture($customVars = Array(), $products = Array()) {

        $this->type = $customVars['payment_issuer'];
        $this->version = 1;
        $this->mode = BuckarooConfig::getMode($this->type);

        $this->data['services'][$this->type]['action'] = 'Capture';
        $this->data['services'][$this->type]['version'] = $this->version;

        $i = 1;
        foreach($products as $p) {
            $this->data['customVars'][$this->type]["ArticleDescription"][$i - 1]["value"] = $p["ArticleDescription"];
            $this->data['customVars'][$this->type]["ArticleDescription"][$i - 1]["group"] = $i;
            $this->data['customVars'][$this->type]["ArticleId"][$i - 1]["value"] = $p["ArticleId"];
            $this->data['customVars'][$this->type]["ArticleId"][$i - 1]["group"] = $i;
            $this->data['customVars'][$this->type]["ArticleQuantity"][$i - 1]["value"] = $p["ArticleQuantity"];
            $this->data['customVars'][$this->type]["ArticleQuantity"][$i - 1]["group"] = $i;
            $this->data['customVars'][$this->type]["ArticleUnitprice"][$i - 1]["value"] = $p["ArticleUnitprice"];
            $this->data['customVars'][$this->type]["ArticleUnitprice"][$i - 1]["group"] = $i;
            $this->data['customVars'][$this->type]["ArticleVatcategory"][$i - 1]["value"] = $p["ArticleVatcategory"];
            $this->data['customVars'][$this->type]["ArticleVatcategory"][$i - 1]["group"] = $i;
            $i++;
        }

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

        return $this->CaptureGlobal();
    }

    /**
     * @access public
     * @return callable parent::checkRefundData($data);
     * @param $data array
     * @throws Exception
     */
    public function checkRefundData($data){
        //Check if order is refundable
        //AFTERPAY
        foreach ($data as $itemKey) {
            if (empty($itemKey['total']) && !empty($itemKey['tax'])) {
                throw new Exception( 'Tax only cannot be refund' );
            }
        }
        $order_id = null;

        if (checkForSequentialNumbersPlugin()){
            if (function_exists('wc_seq_order_number_pro')) {
                $order_id = wc_seq_order_number_pro()->find_order_by_order_number( $order_id );
            } elseif (function_exists('wc_sequential_order_numbers')) {
                $order_id = wc_sequential_order_numbers()->find_order_by_order_number( $order_id );
            }
            $order = wc_get_order($order_id);
        } else {
            $order = wc_get_order( $this->orderId );
        }

        $wooRoundingOption = get_option('woocommerce_tax_round_at_subtotal');
        $wooPriceNumDecimals = (int)get_option('woocommerce_price_num_decimals');

        $items = $order->get_items();
        $shippingItems = $order->get_items('shipping');
        $feeItems = $order->get_items('fee');

        $shippingCostWithoutTax = (float) $order->get_shipping_total();
        $shippingTax = (float)$order->get_shipping_tax();
        $shippingCosts = round($shippingCostWithoutTax, $wooPriceNumDecimals) + round($shippingTax, $wooPriceNumDecimals);
        $shippingRefundedCosts = 0.00;
        $shippingAlreadyRefunded = $order->get_total_shipping_refunded();

        foreach ($items as $item_id => $item_data) {
            if ($items[$item_id] instanceof WC_Order_Item_Product && isset($data[$item_id])) {

                $itemPrice = 0;
                $orderItemRefunded = $order->get_total_refunded_for_item( $item_id );
                $itemTotal = $items[$item_id]->get_total();
                $itemQuantity = $items[$item_id]->get_quantity();
                $itemPrice = round($itemTotal / $itemQuantity, $wooPriceNumDecimals);

                $tax = $items[$item_id]->get_taxes();
                $taxId = 3;

                if (!empty($tax['total'])) {
                    foreach ($tax['total'] as $key => $value) {
                        $taxId = $key;
                    }
                }

                $itemTax = $items[$item_id]->get_total_tax();
                $itemRefundedTax = $order->get_tax_refunded_for_item($item_id, $taxId);
                // FOR AFTERPAY
                if ( empty($data[$item_id]['qty']) ) {
                    throw new Exception('Product quantity doesn`t choose');
                }

                // FOR AFTERPAY
                if ((float)$itemPrice * $data[$item_id]['qty'] !== (float)round($data[$item_id]['total'], $wooPriceNumDecimals)) {
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

                if (round($itemRefundedTax, $wooPriceNumDecimals) - round($itemTax, $wooPriceNumDecimals) > 0.01 ) {
                    throw new Exception('Incorrect refund tax price');
                }
            }
        }

        foreach ($shippingItems as $shipping_item_id => $item_data) {
            if ($shippingItems[$shipping_item_id] instanceof WC_Order_Item_Shipping && isset($data[$shipping_item_id])) {
                if (array_key_exists('total', $data[$shipping_item_id])) {
                    $shippingRefundedCosts  += $data[$shipping_item_id]['total'];
                }
                if (array_key_exists('tax', $data[$shipping_item_id])) {
                    $shippingRefundedCosts  += $data[$shipping_item_id]['tax'];
                }
            }
        }

        foreach ($feeItems as $item_id => $item_data) {
            $feeRefunded = $order->get_qty_refunded_for_item($item_id, 'fee');
            $feeCost = $feeItems[$item_id]->get_total();
            $feeTax = $feeItems[$item_id]->get_taxes();
            if (!empty($feeTax['total'])) {
                foreach ($feeTax['total'] as $taxFee) {
                    $feeCost += round((float)$taxFee, 2);
                }
            }
            if ($feeRefunded > 1) {
                throw new Exception('Payment fee already refunded');
            }
            if (!empty($data[$item_id]['total'])) {
                $totalFeePrice = round((float)$data[$item_id]['total'] + (float)$data[$item_id]['tax'],2);
                if ( abs(($totalFeePrice - $feeCost)/$feeCost) > 0.00001 ) { //$totalFeePrice > $feeCost
                    throw new Exception('Enter valid payment fee:' . $feeCost . esc_attr(get_woocommerce_currency()) );
                } elseif( abs(($feeCost - $totalFeePrice)/$totalFeePrice) > 0.00001 ) { //$totalFeePrice < $feeCost
                    $balance = $feeCost - $totalFeePrice;
                    throw new Exception('Please add ' . $balance . ' ' . esc_attr(get_woocommerce_currency()) . ' to full refund payment fee cost' );
                }
            }
        }
        if ($shippingAlreadyRefunded > $shippingCosts) {
            throw new Exception('Shipping price already refunded');
        }
        if (((float)$shippingCosts !== (float)$shippingRefundedCosts || abs($shippingCosts - $shippingRefundedCosts) > 0.01) && !empty($shippingRefundedCosts)) {
            throw new Exception('Incorrect refund shipping price. Please check refund shipping price and tax amounts');
        }
    }
}
?>
