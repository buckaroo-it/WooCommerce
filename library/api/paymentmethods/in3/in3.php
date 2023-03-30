<?php
require_once dirname(__FILE__) . '/../paymentmethod.php';

/**
 * @package Buckaroo
 */
class BuckarooIn3 extends BuckarooPaymentMethod
{
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
    public $CustomerIPAddress;
    public $Accept;
    public $InvoiceDate;
    public $cocNumber;
    public $companyName;
    public $orderId;

    /**
     * @access public
     * @param string $type
     */
    public function __construct()
    {
        $this->type    = 'Capayable';
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
    public function PayIn3($products, $action)
    {
        $this->setParameter("customParameters", ["order_id" => $this->getRealOrderId()]);
        $this->setCustomVar("CustomerType", ["value" => "Debtor"]);
        $this->setCustomVar("InvoiceDate", ["value" => $this->InvoiceDate]);

        $this->setCustomVar(
            [
                "LastName" => $this->BillingLastName,
                "Culture" =>  'nl-NL',
                "Initials" => $this->BillingInitials,
                "BirthDate" => $this->BillingBirthDate
            ],
            null,
            'Person'
        );

        $this->setCustomVar(
            [
                "Street" => $this->BillingStreet,
                "HouseNumber" => isset($this->BillingHouseNumber) ? $this->BillingHouseNumber . ' ' : $this->BillingHouseNumber,
                "HouseNumberSuffix" => $this->BillingHouseNumberSuffix,
                "ZipCode" => $this->BillingPostalCode,
                "City" => $this->BillingCity,
                "Country" => $this->BillingCountry,
            ],
            null,
            'Address'
        );
        $this->setCustomVar("Phone", $this->BillingPhoneNumber, 'Phone');
        $this->setCustomVar("Email", $this->BillingEmail, 'Email');

        foreach ($products as $pos => $product) {
            $this->setDefaultProductParams($product, $pos);
        }
        return parent::$action();
    }

    private function setDefaultProductParams($product, $position)
    {

        $productData = [
            'Name' => $product["description"],
            'Code' => $product["identifier"],
            'Quantity' => $product["quantity"],
            'Price' => $product["price"],
        ];

        
        $this->setCustomVarsAtPosition(
            $productData,
            $position,
            'ProductLine'
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
    public function In3Refund()
    {
        $this->setServiceTypeActionAndVersion(
            'Capayable',
            'Refund',
            BuckarooPaymentMethod::VERSION_ONE
        );

        return $this->RefundGlobal();
    }
}
