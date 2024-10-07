<?php

namespace Buckaroo\Woocommerce\Gateways;

use Buckaroo\Woocommerce\Components\OrderArticles;
use Buckaroo\Woocommerce\Components\OrderDetails;
use Buckaroo\Woocommerce\Services\HttpRequest;
use WC_Order;

class AbstractPaymentProcessor extends AbstractPaymentHelper
{
    const TYPE_PAY = 'pay';
    const TYPE_CAPTURE = 'capture';
    const TYPE_REFUND = 'refund';
    const VERSION_ZERO = 0;
    const VERSION_ONE = 1;
    const VERSION_TWO = 2;
    public const REQUEST_TYPE_DATA_REQUEST = 'DataRequest';
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
    public $sellerprotection = 0;
    public $CreditCardDataEncrypted;
    public $real_order_id;
    public AbstractPaymentGateway $gateway;
    protected $type;
    protected $data = array();
    protected $requestType = 'TransactionRequest';
    protected OrderDetails $order_details;
    protected OrderArticles $order_articles;
    private HttpRequest $request;

    public function __construct(
        AbstractPaymentGateway $gateway,
        HttpRequest            $request,
        OrderDetails           $order_details,
        OrderArticles          $order_articles
    )
    {
        $this->gateway = $gateway;
        $this->request = $request;
        $this->order_details = $order_details;
        $this->order_articles = $order_articles;
    }

    /**
     * Calculate checksum from iban and confirm validity of iban
     *
     * @access public
     * @param string $iban
     * @return boolean
     */
    public static function isIBAN($iban)
    {
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

    public function setRequestType(string $type)
    {
        $this->requestType = $type;
    }

    public function getAction(): string
    {
        return 'pay';
    }

    public function getBody(): array
    {
        return array_merge(
            $this->getMethodBody(),
            [
                'order' => (string)$this->get_order()->get_id(),
                'invoice' => $this->get_invoice_number(),
                'amountDebit' => number_format((float)$this->get_order()->get_total('edit'), 2, '.', ''),
                'currency' => get_woocommerce_currency(),
                'returnURL' => $this->get_return_url(),
                'cancelURL' => $this->get_return_url(),
                'pushURL' => $this->get_push_url(),
                'additionalParameters' => [
                    'real_order_id' => $this->get_order()->get_id(),
                ],

                'description' => $this->get_description(),
                'clientIP' => $this->get_ip(),
            ]
        );
    }

    protected function getMethodBody(): array
    {
        return array();
    }

    /**
     * Get order
     *
     * @return WC_Order
     */
    protected function get_order(): WC_Order
    {
        return $this->order_details->get_order();
    }

    private function get_invoice_number(): string
    {
        if (in_array($this->gateway->id, ["buckaroo_afterpaynew", "buckaroo_afterpay"])) {
            return (string)$this->get_order()->get_order_number() . time();
        }
        return (string)$this->get_order()->get_order_number();
    }

    public function get_return_url($order = null): string
    {
        return add_query_arg('wc-api', 'WC_Gateway_' . ucfirst($this->gateway->id), home_url('/'));
    }

    /**
     * Get push url
     *
     * @return string
     */
    private function get_push_url(): string
    {
        return add_query_arg('wc-api', 'wc_push_buckaroo', home_url('/'));
    }

    /**
     * Get the parsed label, we replace the template variables with the values
     *
     * @return string
     */
    public function get_description(): string
    {
        $label = $this->gateway->get_option('transactiondescription', 'Order #' . $this->get_order()->get_order_number());

        $label = preg_replace('/\{order_number\}/', $this->get_order()->get_order_number(), $label);
        $label = preg_replace('/\{shop_name\}/', get_bloginfo('name'), $label);

        $products = $this->get_order()->get_items('line_item');
        if (count($products)) {
            $label = preg_replace('/\{product_name\}/', array_values($products)[0]->get_name(), $label);
        }

        $label = preg_replace("/\r?\n|\r/", '', $label);

        return mb_substr($label, 0, 244);
    }

    /**
     * Get ip
     *
     * @return string
     */
    protected function get_ip(): string
    {
        $ipaddress = '';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (!empty($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['HTTP_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        } else {
            $ipaddress = 'UNKNOWN';
        }
        $ex = explode(",", sanitize_text_field($ipaddress));
        if (filter_var($ex[0], FILTER_VALIDATE_IP)) {
            return trim($ex[0]);
        }
        return "";
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param mixed $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * Get real order id
     *
     * @return int
     */
    public function getRealOrderId()
    {
        if (is_int($this->real_order_id)) {
            return $this->real_order_id;
        }
        return $this->orderId;
    }

    public function order_number_shortcode()
    {
        return $this->data['description'] . ' ' . $this->invoiceId;
    }

    /**
     * Get ip
     *
     * @return string
     */
    protected function getIp(): string
    {
        $ipaddress = '';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (!empty($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['HTTP_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        } else {
            $ipaddress = 'UNKNOWN';
        }
        $ex = explode(",", sanitize_text_field($ipaddress));
        if (filter_var($ex[0], FILTER_VALIDATE_IP)) {
            return trim($ex[0]);
        }
        return "";
    }

    /**
     * Get address component
     *
     * @param string $type
     * @param string $key
     * @param string $default
     *
     * @return mixed
     */
    protected function getAddress(string $type, string $key, $default = '')
    {
        return $this->order_details->get($type . "_" . $key, $default);
    }

    /**
     * Get order articles
     *
     * @return array
     */
    protected function getArticles(): array
    {
        return $this->order_articles->get_products_for_payment();
    }

    protected function request_string(string $key, $default = null): ?string
    {
        $value = $this->request($key);
        if (!is_string($value) || empty(trim($value))) {
            return $default;
        }
        return $value;
    }

    protected function request(string $key, $default = null)
    {
        $value = $this->request->request($key);
        return $value ?? $default;
    }

    /**
     * Get invoice number for refund
     *
     * @return string
     */
    private function getInvoiceNumber()
    {
        return $this->invoiceId;
    }
}
