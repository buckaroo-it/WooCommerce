<?php
require_once dirname(__FILE__) . '/../paymentmethod.php';

/**
 * @package Buckaroo
 */
class BuckarooAfterPayNew extends BuckarooPaymentMethod
{
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
    public $IdentificationNumber;
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
    public $ShippingCostsTax;
    public $CustomerIPAddress;
    public $Accept;
    public $CompanyCOCRegistration;
    public $CompanyName;
    public $CostCentre;
    public $VatNumber;

    /**
     * @access public
     * @param string $type
     */
    public function __construct($type = 'afterpay')
    {
        $this->type    = $type;
        $this->version = '1';
    }

    /**
     * @access public
     * @param array $customVars
     * @return void
     */
    public function Pay($customVars = array())
    {
        return null;
    }

    /**
     * @access public
     * @param array $products
     * @return callable parent::Pay();
     */
    public function PayOrAuthorizeAfterpay($products, $action)
    {

        $billing = [
            "Category" => 'Person',
            "FirstName" => $this->BillingInitials,
            "LastName" => $this->BillingLastName,
            "Street" => $this->BillingStreet,
            "StreetNumber" => $this->BillingHouseNumber . ' ',
            "PostalCode" => $this->BillingPostalCode,
            "City" => $this->BillingCity,
            "Country" => $this->BillingCountry,
            "Email" => $this->BillingEmail,
        ];
        $shipping = [
            "Category" => 'Person',
            "FirstName" => $this->diffAddress($this->ShippingInitials, $this->BillingInitials),
            "LastName" => $this->diffAddress($this->ShippingLastName, $this->BillingLastName),
            "Street" => $this->diffAddress($this->ShippingStreet, $this->BillingStreet),
            "StreetNumber" => $this->diffAddress($this->ShippingHouseNumber, $this->BillingHouseNumber). ' ',
            "PostalCode" => $this->diffAddress($this->ShippingPostalCode, $this->BillingPostalCode),
            "City" => $this->diffAddress($this->ShippingCity, $this->BillingCity),
            "Country" => $this->diffAddress($this->ShippingCountryCode, $this->BillingCountry),
            "Email" => $this->BillingEmail,
        ];


        if (!empty($this->BillingHouseNumberSuffix)) {
            $billing["StreetNumberAdditional"] = $this->BillingHouseNumberSuffix;
        } else {
            unset($this->BillingHouseNumberSuffix);
        }

        if (($this->AddressesDiffer == 'TRUE') && !empty($this->ShippingHouseNumberSuffix)) {
            $shipping["StreetNumberAdditional"] = $this->ShippingHouseNumberSuffix;
        } elseif ($this->AddressesDiffer !== 'TRUE' && !empty($this->BillingHouseNumberSuffix)) {
            $shipping["StreetNumberAdditional"] = $this->BillingHouseNumberSuffix;
        } else {
            unset($this->ShippingHouseNumberSuffix);
        }

        
        if ((isset($this->ShippingCountryCode) && in_array($this->ShippingCountryCode, ['NL', 'BE'])) || (!isset($this->ShippingCountryCode) && in_array($this->BillingCountry, ['NL', 'BE']))) {
            // Send parameters (Salutation, BirthDate, MobilePhone and Phone) if shipping country is NL || BE.
            $billing = array_merge(
                $billing,
                [
                    "Salutation" => $this->BillingGender == '1' ? 'Mr' : 'Mrs',
                    "BirthDate" => $this->BillingBirthDate,
                    "MobilePhone" =>  $this->BillingPhoneNumber,
                    "Phone" =>  $this->BillingPhoneNumber,
                ]
            );
            $shipping = array_merge(
                $shipping,
                [
                    "Salutation" => $this->ShippingGender == '1' ? 'Mr' : 'Mrs',
                    "BirthDate" => $this->BillingBirthDate,
                    "MobilePhone" =>  $this->BillingPhoneNumber,
                    "Phone" =>  $this->BillingPhoneNumber,
                ]
            );
        }

        if ((isset($this->ShippingCountryCode) && ($this->ShippingCountryCode == "FI")) || (!isset($this->ShippingCountryCode) && ($this->BillingCountry == "FI"))) {
            $shipping["IdentificationNumber"] = $this->IdentificationNumber;
            $billing["IdentificationNumber"] = $this->IdentificationNumber;
        }

        $this->setCustomVarsAtPosition($billing, 0, 'BillingCustomer');
        $this->setCustomVarsAtPosition($shipping, 1, 'ShippingCustomer');

        // Merge products with same SKU
        $mergedProducts = array();
        foreach ($products as $product) {
            if (!isset($mergedProducts[$product['ArticleId']])) {
                $mergedProducts[$product['ArticleId']] = $product;
            } else {
                $mergedProducts[$product['ArticleId']]["ArticleQuantity"] += 1;
            }
        }

        $products = $mergedProducts;

        foreach (array_values($products) as $pos => $product) {
            $this->setDefaultProductParams($product, $pos);
            $additonalVars = [
                'Url' => $product["ProductUrl"],
            ];

            if (!empty($p["ImageUrl"])) {
                $additonalVars['ImageUrl'] = $product["ImageUrl"];
            }

            $this->setCustomVarsAtPosition($additonalVars, $pos, 'Article');

        }
        $this->setShipping(count($products));

        return parent::$action();
    }
    private function diffAddress($shippingField, $billingField)
    {
        if ($this->AddressesDiffer == 'TRUE') {
            return $shippingField;
        }
        return $billingField;
    }
    /**
     * Set shipping at postion
     *
     * @param int $position
     *
     * @return void
     */
    public function setShipping($position)
    {
        $this->setCustomVarsAtPosition(
            [
                'Description' => 'Shipping Cost',
                'Identifier' => 'shipping',
                'Quantity' => 1,
                'GrossUnitprice' => (!empty($this->ShippingCosts) ? $this->ShippingCosts : '0'),
                'VatPercentage' => (!empty($this->ShippingCostsTax) ? $this->ShippingCostsTax : '0')

            ],
            $position,
            'Article'
        );
    }
    
    /**
     * Populate generic fields for a refund
     *
     * @access public
     * * @param array $products
     * @throws Exception
     * @return callable $this->RefundGlobal()
     */
    public function AfterPayRefund($products, $issuer)
    {
        $this->setServiceTypeActionAndVersion(
            $issuer,
            'Refund',
            BuckarooPaymentMethod::VERSION_ONE
        );

        foreach (array_values($products) as $pos => $product) {
            $this->setDefaultProductParams($product, $pos);
            $this->setCustomVarAtPosition(
                'RefundType',
                ($product["ArticleId"] == BuckarooConfig::SHIPPING_SKU ? "Refund" : "Return"),
                $pos,
                'Article'
            );
        }
        return $this->RefundGlobal();
    }

    /**
     * @access public
     * @param array $customVars
     * * @param array $products
     * @return callable parent::PayGlobal()
     */
    public function Capture($customVars = array(), $products = array())
    {

        $this->setServiceTypeActionAndVersion(
            $customVars['payment_issuer'],
            'Capture',
            BuckarooPaymentMethod::VERSION_ONE
        );

        foreach (array_values($products) as $pos => $product) {
            $this->setDefaultProductParams($product, $pos);
        }

        return $this->CaptureGlobal();
    }
    public function setDefaultProductParams($product, $position)
    {
        $this->setCustomVarsAtPosition(
            [
                'Description' => $product["ArticleDescription"],
                'Identifier' => $product["ArticleId"],
                'Quantity' => $product["ArticleQuantity"],
                'GrossUnitprice' => $product["ArticleUnitprice"],
                'VatPercentage' => !empty($p["ArticleVatcategory"]) ? $p["ArticleVatcategory"] : 0,

            ],
            $position,
            'Article'
        );
    }
    public function checkRefundData($data)
    {
        //Check if order is refundable
        //AFTERPAY
        foreach ($data as $itemKey) {
            if (empty($itemKey['total']) && !empty($itemKey['tax'])) {
                throw new Exception('Tax only cannot be refund');
            }
        }
        $order = wc_get_order($this->orderId);
        $items         = $order->get_items();
        $shippingItems = $order->get_items('shipping');
        $feeItems      = $order->get_items('fee');

        $shippingCostWithoutTax  = (float) $order->get_shipping_total();
        $shippingTax             = (float) $order->get_shipping_tax();
        $shippingCosts           = roundAmount($shippingCostWithoutTax) + roundAmount($shippingTax);
        $shippingRefundedCosts   = 0.00;
        $shippingAlreadyRefunded = $order->get_total_shipping_refunded();

        foreach ($items as $item_id => $item_data) {
            if ($items[$item_id] instanceof WC_Order_Item_Product && isset($data[$item_id])) {

                $itemPrice         = 0;
                $orderItemRefunded = $order->get_total_refunded_for_item($item_id);
                $itemTotal         = $items[$item_id]->get_total();
                $itemQuantity      = $items[$item_id]->get_quantity();
                $itemPrice         = roundAmount($itemTotal / $itemQuantity);

                $tax   = $items[$item_id]->get_taxes();
                $taxId = 3;

                if (!empty($tax['total'])) {
                    foreach ($tax['total'] as $key => $value) {
                        $taxId = $key;
                    }
                }

                $itemTax         = $items[$item_id]->get_total_tax();
                $itemRefundedTax = $order->get_tax_refunded_for_item($item_id, $taxId);
                // FOR AFTERPAY
                if (empty($data[$item_id]['qty'])) {
                    throw new Exception('Product quantity doesn`t choose');
                }

                // FOR AFTERPAY
                if ((float) $itemPrice * $data[$item_id]['qty'] !== (float) roundAmount($data[$item_id]['total'])) {
                    throw new Exception('Incorrect entered product price. Please check refund product price and tax amounts');
                }

                if (!empty($data[$item_id]['qty'])) {
                    $item_refunded = $order->get_qty_refunded_for_item($item_id);
                    if ($itemQuantity === abs($item_refunded) - $data[$item_id]['qty']) {
                        throw new Exception('Product already refunded');
                    } elseif ($itemQuantity < abs($item_refunded)) {
                        $availableRefundQty = $itemQuantity - (abs($item_refunded) - $data[$item_id]['qty']);
                        $message            = $availableRefundQty . ' item(s) can be refund';
                        throw new Exception($message);
                    }
                }

                if (roundAmount($itemRefundedTax) - roundAmount($itemTax) > 0.01) {
                    throw new Exception('Incorrect refund tax price');
                }
            }
        }

        foreach ($shippingItems as $shipping_item_id => $item_data) {
            if ($shippingItems[$shipping_item_id] instanceof WC_Order_Item_Shipping && isset($data[$shipping_item_id])) {
                if (array_key_exists('total', $data[$shipping_item_id])) {
                    $shippingRefundedCosts += $data[$shipping_item_id]['total'];
                }
                if (array_key_exists('tax', $data[$shipping_item_id])) {
                    $shippingRefundedCosts += $data[$shipping_item_id]['tax'];
                }
            }
        }

        foreach ($feeItems as $item_id => $item_data) {
            $feeRefunded = $order->get_qty_refunded_for_item($item_id, 'fee');
            $feeCost     = $feeItems[$item_id]->get_total();
            $feeTax      = $feeItems[$item_id]->get_taxes();
            if (!empty($feeTax['total'])) {
                foreach ($feeTax['total'] as $taxFee) {
                    $feeCost += roundAmount((float) $taxFee);
                }
            }
            if ($feeRefunded > 1) {
                throw new Exception('Payment fee already refunded');
            }
            if (!empty($data[$item_id]['total'])) {
                $totalFeePrice = roundAmount((float) $data[$item_id]['total'] + (float) $data[$item_id]['tax']);
                if (abs($totalFeePrice) - abs($feeCost) < 0 && abs($totalFeePrice - $feeCost) > 0.01) {
                    // abs(($totalFeePrice - $feeCost)/$feeCost) > 0.00001
                    throw new Exception('Enter valid payment fee:' . $feeCost . esc_attr(get_woocommerce_currency()));
                } elseif (abs($feeCost) - abs($totalFeePrice) < 0 && abs($feeCost - $totalFeePrice) > 0.01) {
                    //abs(($feeCost - $totalFeePrice)/$totalFeePrice) > 0.00001 )
                    $balance = $feeCost - $totalFeePrice;
                    throw new Exception('Please add ' . $balance . ' ' . esc_attr(get_woocommerce_currency()) . ' to full refund payment fee cost');
                }
            }
        }
        if ($shippingAlreadyRefunded > $shippingCosts) {
            throw new Exception('Shipping price already refunded');
        }
        if (((float) $shippingCosts !== (float) $shippingRefundedCosts || abs($shippingCosts - $shippingRefundedCosts) > 0.01) && !empty($shippingRefundedCosts)) {
            throw new Exception('Incorrect refund shipping price. Please check refund shipping price and tax amounts');
        }
    }
}
