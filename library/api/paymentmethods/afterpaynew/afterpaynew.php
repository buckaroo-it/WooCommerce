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
        $this->mode    = BuckarooConfig::getMode('AFTERPAY');
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

        $this->data['customVars'][$this->type]["Category"][0]["value"] = 'Person';
        $this->data['customVars'][$this->type]["Category"][0]["group"] = 'BillingCustomer';
        $this->data['customVars'][$this->type]["Category"][1]["value"] = 'Person';
        $this->data['customVars'][$this->type]["Category"][1]["group"] = 'ShippingCustomer';

        $this->data['customVars'][$this->type]["FirstName"][0]["value"] = $this->BillingInitials;
        $this->data['customVars'][$this->type]["FirstName"][0]["group"] = 'BillingCustomer';
        $this->data['customVars'][$this->type]['FirstName'][1]["value"] = ($this->AddressesDiffer == 'TRUE' && !empty($this->ShippingInitials)) ? $this->ShippingInitials : $this->BillingInitials;
        $this->data['customVars'][$this->type]["FirstName"][1]["group"] = 'ShippingCustomer';

        $this->data['customVars'][$this->type]["LastName"][0]["value"] = $this->BillingLastName;
        $this->data['customVars'][$this->type]["LastName"][0]["group"] = 'BillingCustomer';
        $this->data['customVars'][$this->type]['LastName'][1]["value"] = ($this->AddressesDiffer == 'TRUE' && !empty($this->ShippingLastName)) ? $this->ShippingLastName : $this->BillingLastName;
        $this->data['customVars'][$this->type]["LastName"][1]["group"] = 'ShippingCustomer';

        $this->data['customVars'][$this->type]["Street"][0]["value"] = $this->BillingStreet;
        $this->data['customVars'][$this->type]["Street"][0]["group"] = 'BillingCustomer';
        $this->data['customVars'][$this->type]['Street'][1]["value"] = ($this->AddressesDiffer == 'TRUE') ? $this->ShippingStreet : $this->BillingStreet;
        $this->data['customVars'][$this->type]["Street"][1]["group"] = 'ShippingCustomer';

        $this->data['customVars'][$this->type]["StreetNumber"][0]["value"] = $this->BillingHouseNumber . ' ';
        $this->data['customVars'][$this->type]["StreetNumber"][0]["group"] = 'BillingCustomer';
        $this->data['customVars'][$this->type]['StreetNumber'][1]["value"] = ($this->AddressesDiffer == 'TRUE') ? $this->ShippingHouseNumber . ' ' : $this->BillingHouseNumber . ' ';
        $this->data['customVars'][$this->type]["StreetNumber"][1]["group"] = 'ShippingCustomer';

        if (!empty($this->BillingHouseNumberSuffix)) {
            $this->data['customVars'][$this->type]["StreetNumberAdditional"][0]["value"] = $this->BillingHouseNumberSuffix;
            $this->data['customVars'][$this->type]["StreetNumberAdditional"][0]["group"] = 'BillingCustomer';
        } else {
            unset($this->BillingHouseNumberSuffix);
        }

        if (($this->AddressesDiffer == 'TRUE') && !empty($this->ShippingHouseNumberSuffix)) {
            $this->data['customVars'][$this->type]['StreetNumberAdditional'][1]["value"] = $this->ShippingHouseNumberSuffix;
            $this->data['customVars'][$this->type]["StreetNumberAdditional"][1]["group"] = 'ShippingCustomer';
        } elseif ($this->AddressesDiffer !== 'TRUE' && !empty($this->BillingHouseNumberSuffix)) {
            $this->data['customVars'][$this->type]['StreetNumberAdditional'][1]["value"] = $this->BillingHouseNumberSuffix;
            $this->data['customVars'][$this->type]["StreetNumberAdditional"][1]["group"] = 'ShippingCustomer';
        } else {
            unset($this->ShippingHouseNumberSuffix);
        }

        $this->data['customVars'][$this->type]["PostalCode"][0]["value"] = $this->BillingPostalCode;
        $this->data['customVars'][$this->type]["PostalCode"][0]["group"] = 'BillingCustomer';
        $this->data['customVars'][$this->type]['PostalCode'][1]["value"] = ($this->AddressesDiffer == 'TRUE') ? $this->ShippingPostalCode : $this->BillingPostalCode;
        $this->data['customVars'][$this->type]["PostalCode"][1]["group"] = 'ShippingCustomer';

        $this->data['customVars'][$this->type]["City"][0]["value"] = $this->BillingCity;
        $this->data['customVars'][$this->type]["City"][0]["group"] = 'BillingCustomer';
        $this->data['customVars'][$this->type]['City'][1]["value"] = ($this->AddressesDiffer == 'TRUE') ? $this->ShippingCity : $this->BillingCity;
        $this->data['customVars'][$this->type]["City"][1]["group"] = 'ShippingCustomer';

        $this->data['customVars'][$this->type]["Country"][0]["value"] = $this->BillingCountry;
        $this->data['customVars'][$this->type]["Country"][0]["group"] = 'BillingCustomer';
        $this->data['customVars'][$this->type]['Country'][1]["value"] = ($this->AddressesDiffer == 'TRUE') ? $this->ShippingCountryCode : $this->BillingCountry;
        $this->data['customVars'][$this->type]["Country"][1]["group"] = 'ShippingCustomer';

        $this->data['customVars'][$this->type]["Email"][0]["value"] = $this->BillingEmail;
        $this->data['customVars'][$this->type]["Email"][0]["group"] = 'BillingCustomer';
        $this->data['customVars'][$this->type]["Email"][1]["value"] = $this->BillingEmail;
        $this->data['customVars'][$this->type]["Email"][1]["group"] = 'ShippingCustomer';

        if ((isset($this->ShippingCountryCode) && in_array($this->ShippingCountryCode, ['NL', 'BE'])) || (!isset($this->ShippingCountryCode) && in_array($this->BillingCountry, ['NL', 'BE']))) {
            // Send parameters (Salutation, BirthDate, MobilePhone and Phone) if shipping country is NL || BE.
            $this->data['customVars'][$this->type]["Salutation"][0]["value"] = ($this->BillingGender) == '1' ? 'Mr' : 'Mrs';
            $this->data['customVars'][$this->type]["Salutation"][0]["group"] = 'BillingCustomer';
            $this->data['customVars'][$this->type]["Salutation"][1]["value"] = ($this->ShippingGender) == '1' ? 'Mr' : 'Mrs';
            $this->data['customVars'][$this->type]["Salutation"][1]["group"] = 'ShippingCustomer';

            $this->data['customVars'][$this->type]["BirthDate"][0]["value"] = $this->BillingBirthDate;
            $this->data['customVars'][$this->type]["BirthDate"][0]["group"] = 'BillingCustomer';
            $this->data['customVars'][$this->type]["BirthDate"][1]["value"] = $this->BillingBirthDate;
            $this->data['customVars'][$this->type]["BirthDate"][1]["group"] = 'ShippingCustomer';

            $this->data['customVars'][$this->type]["MobilePhone"][0]["value"] = $this->BillingPhoneNumber;
            $this->data['customVars'][$this->type]["MobilePhone"][0]["group"] = 'BillingCustomer';
            $this->data['customVars'][$this->type]["MobilePhone"][1]["value"] = $this->BillingPhoneNumber;
            $this->data['customVars'][$this->type]["MobilePhone"][1]["group"] = 'ShippingCustomer';

            $this->data['customVars'][$this->type]["Phone"][0]["value"] = $this->BillingPhoneNumber;
            $this->data['customVars'][$this->type]["Phone"][0]["group"] = 'BillingCustomer';
            $this->data['customVars'][$this->type]["Phone"][1]["value"] = $this->BillingPhoneNumber;
            $this->data['customVars'][$this->type]["Phone"][1]["group"] = 'ShippingCustomer';
        }

        if ((isset($this->ShippingCountryCode) && ($this->ShippingCountryCode == "FI")) || (!isset($this->ShippingCountryCode) && ($this->BillingCountry == "FI"))) {
            // Send parameter IdentificationNumber if country equals FI.
            $this->data['customVars'][$this->type]["IdentificationNumber"][0]["value"] = $this->IdentificationNumber;
            $this->data['customVars'][$this->type]["IdentificationNumber"][0]["group"] = 'BillingCustomer';
            // Send parameter IdentificationNumber if country equals FI.
            $this->data['customVars'][$this->type]["IdentificationNumber"][1]["value"] = $this->IdentificationNumber;
            $this->data['customVars'][$this->type]["IdentificationNumber"][1]["group"] = 'ShippingCustomer';
        }

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

        $i = 1;
        foreach ($products as $p) {
            $this->data['customVars'][$this->type]["Description"][$i - 1]["value"]    = $p["ArticleDescription"];
            $this->data['customVars'][$this->type]["Description"][$i - 1]["group"]    = 'Article';
            $this->data['customVars'][$this->type]["Identifier"][$i - 1]["value"]     = $p["ArticleId"];
            $this->data['customVars'][$this->type]["Identifier"][$i - 1]["group"]     = 'Article';
            $this->data['customVars'][$this->type]["Quantity"][$i - 1]["value"]       = $p["ArticleQuantity"];
            $this->data['customVars'][$this->type]["Quantity"][$i - 1]["group"]       = 'Article';
            $this->data['customVars'][$this->type]["GrossUnitprice"][$i - 1]["value"] = $p["ArticleUnitprice"];
            $this->data['customVars'][$this->type]["GrossUnitprice"][$i - 1]["group"] = 'Article';
            $this->data['customVars'][$this->type]["VatPercentage"][$i - 1]["value"]  = isset($p["ArticleVatcategory"]) ? $p["ArticleVatcategory"] : 0;
            $this->data['customVars'][$this->type]["VatPercentage"][$i - 1]["group"]  = 'Article';
            $this->data['customVars'][$this->type]["Url"][$i - 1]["value"]            = $p["ProductUrl"];
            $this->data['customVars'][$this->type]["Url"][$i - 1]["group"]            = 'Article';
            if (!empty($p["ImageUrl"])) {
                $this->data['customVars'][$this->type]["ImageUrl"][$i - 1]["value"]       = $p["ImageUrl"];
                $this->data['customVars'][$this->type]["ImageUrl"][$i - 1]["group"]       = 'Article';
            }
            $i++;
        }

        $this->data['customVars'][$this->type]["Description"][$i]["value"]    = 'Shipping Cost';
        $this->data['customVars'][$this->type]["Description"][$i]["group"]    = 'Article';
        $this->data['customVars'][$this->type]["Identifier"][$i]["value"]     = 'shipping';
        $this->data['customVars'][$this->type]["Identifier"][$i]["group"]     = 'Article';
        $this->data['customVars'][$this->type]["Quantity"][$i]["value"]       = '1';
        $this->data['customVars'][$this->type]["Quantity"][$i]["group"]       = 'Article';
        $this->data['customVars'][$this->type]["GrossUnitprice"][$i]["value"] = (!empty($this->ShippingCosts) ? $this->ShippingCosts : '0');
        $this->data['customVars'][$this->type]["GrossUnitprice"][$i]["group"] = 'Article';
        $this->data['customVars'][$this->type]["VatPercentage"][$i]["value"]  = (!empty($this->ShippingCostsTax) ? $this->ShippingCostsTax : '0');
        $this->data['customVars'][$this->type]["VatPercentage"][$i]["group"]  = 'Article';

        return parent::$action();
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
        $this->type    = $issuer;
        $this->version = 1;
        $this->mode    = BuckarooConfig::getMode($this->type);

        $this->data['services'][$this->type]['action']  = 'Refund';
        $this->data['services'][$this->type]['version'] = $this->version;

        $i = 1;
        foreach ($products as $p) {
            $this->data['customVars'][$this->type]["Description"][$i - 1]["value"]    = $p["ArticleDescription"];
            $this->data['customVars'][$this->type]["Description"][$i - 1]["group"]    = 'Article';
            $this->data['customVars'][$this->type]["Identifier"][$i - 1]["value"]     = $p["ArticleId"];
            $this->data['customVars'][$this->type]["Identifier"][$i - 1]["group"]     = 'Article';
            $this->data['customVars'][$this->type]["Quantity"][$i - 1]["value"]       = $p["ArticleQuantity"];
            $this->data['customVars'][$this->type]["Quantity"][$i - 1]["group"]       = 'Article';
            $this->data['customVars'][$this->type]["GrossUnitprice"][$i - 1]["value"] = $p["ArticleUnitprice"];
            $this->data['customVars'][$this->type]["GrossUnitprice"][$i - 1]["group"] = 'Article';
            $this->data['customVars'][$this->type]["VatPercentage"][$i - 1]["value"]  = !empty($p["ArticleVatcategory"]) ? $p["ArticleVatcategory"] : 0;
            $this->data['customVars'][$this->type]["VatPercentage"][$i - 1]["group"]  = 'Article';
            $this->data['customVars'][$this->type]["RefundType"][$i - 1]["value"]     = ($p["ArticleId"] == BuckarooConfig::SHIPPING_SKU ? "Refund" : "Return");
            $this->data['customVars'][$this->type]["RefundType"][$i - 1]["group"]     = 'Article';
            $i++;
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

        $this->type    = $customVars['payment_issuer'];
        $this->version = 1;
        $this->mode    = BuckarooConfig::getMode($this->type);

        $this->data['services'][$this->type]['action']  = 'Capture';
        $this->data['services'][$this->type]['version'] = $this->version;

        $i = 1;
        foreach ($products as $p) {
            $this->data['customVars'][$this->type]["Description"][$i - 1]["value"]    = $p["ArticleDescription"];
            $this->data['customVars'][$this->type]["Description"][$i - 1]["group"]    = 'Article';
            $this->data['customVars'][$this->type]["Identifier"][$i - 1]["value"]     = $p["ArticleId"];
            $this->data['customVars'][$this->type]["Identifier"][$i - 1]["group"]     = 'Article';
            $this->data['customVars'][$this->type]["Quantity"][$i - 1]["value"]       = $p["ArticleQuantity"];
            $this->data['customVars'][$this->type]["Quantity"][$i - 1]["group"]       = 'Article';
            $this->data['customVars'][$this->type]["GrossUnitprice"][$i - 1]["value"] = $p["ArticleUnitprice"];
            $this->data['customVars'][$this->type]["GrossUnitprice"][$i - 1]["group"] = 'Article';
            $this->data['customVars'][$this->type]["VatPercentage"][$i - 1]["value"]  = !empty($p["ArticleVatcategory"]) ? $p["ArticleVatcategory"] : 0;
            $this->data['customVars'][$this->type]["VatPercentage"][$i - 1]["group"]  = 'Article';
            $i++;
        }

        return $this->CaptureGlobal();
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
                    $feeCost += roundAmount((float) $taxFee, 2);
                }
            }
            if ($feeRefunded > 1) {
                throw new Exception('Payment fee already refunded');
            }
            if (!empty($data[$item_id]['total'])) {
                $totalFeePrice = roundAmount((float) $data[$item_id]['total'] + (float) $data[$item_id]['tax'], 2);
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
