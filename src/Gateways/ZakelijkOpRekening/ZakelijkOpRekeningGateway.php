<?php

namespace Buckaroo\Woocommerce\Gateways\ZakelijkOpRekening;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;
use Buckaroo\Woocommerce\Gateways\AbstractProcessor;
use Buckaroo\Woocommerce\Gateways\ZakelijkOpRekening\Sdk\ZakelijkOpRekeningPaymentMethod;
use Buckaroo\Woocommerce\PaymentProcessors\Actions\CaptureAction;
use Buckaroo\Woocommerce\PaymentProcessors\ReturnProcessor;
use Buckaroo\Woocommerce\Services\BuckarooClient;
use Buckaroo\Woocommerce\Services\Helper;
use Buckaroo\Woocommerce\Services\Logger;
use BuckarooDeps\Buckaroo\Services\PayloadService;
use BuckarooDeps\Buckaroo\Transaction\Response\TransactionResponse;
use WC_Order;

/**
 * "Zakelijk op rekening" (powered by ABN AMRO) — a B2B buy-now-pay-later
 * method based on the In3 service. Only the separate Authorize / Capture flow
 * is supported. Available for companies located in The Netherlands, for orders
 * between EUR 250 and EUR 25.000.
 */
class ZakelijkOpRekeningGateway extends AbstractPaymentGateway
{
    public const PAYMENT_CLASS = ZakelijkOpRekeningProcessor::class;

    /**
     * Scheme order-amount limits for this method (in the shop currency, EUR).
     */
    public const MIN_AMOUNT = 250.0;

    public const MAX_AMOUNT = 25000.0;

    /**
     * This gateway supports the separate capture flow.
     */
    public bool $capturable = true;

    /**
     * Zakelijk op rekening only supports EUR.
     *
     * @var array<string>
     */
    protected array $supportedCurrencies = ['EUR'];

    public function __construct()
    {
        $this->id = 'buckaroo_zakelijkoprekening';
        $this->has_fields = true;
        $this->method_title = 'Buckaroo Zakelijk op rekening';
        $this->title = 'Zakelijk op rekening';

        $this->setCountry();

        parent::__construct();

        $this->setIcon('svg/zakelijk-op-rekening-abn-amro.svg');
        $this->addRefundSupport();
    }

    /**
     * Reuse the In3 service code on the Buckaroo API.
     */
    public function getServiceCode(?AbstractProcessor $processor = null)
    {
        return 'in3';
    }

    /** {@inheritDoc} */
    public function init_form_fields()
    {
        parent::init_form_fields();

        // Pre-fill the scheme limits so the method is hidden outside the
        // supported order-amount range by default.
        if (isset($this->form_fields['minvalue'])) {
            $this->form_fields['minvalue']['default'] = (string) self::MIN_AMOUNT;
        }
        if (isset($this->form_fields['maxvalue'])) {
            $this->form_fields['maxvalue']['default'] = (string) self::MAX_AMOUNT;
        }
    }

    /**
     * Validate the checkout fields (classic checkout).
     *
     * @return void
     */
    public function validate_fields()
    {
        $country = $this->request->input('billing_country');
        if (empty($country)) {
            $customer = (function_exists('WC') && WC()) ? WC()->customer : null;
            $country = $customer ? ($customer->get_billing_country() ?: $customer->get_shipping_country()) : $this->country;
        }

        if (! empty($country) && $country !== 'NL') {
            wc_add_notice(
                __('Zakelijk op rekening is only available for companies located in The Netherlands.', 'wc-buckaroo-bpe-gateway'),
                'error'
            );
        }

        if ($this->getCheckoutCompany() === '') {
            wc_add_notice(
                __('A company name is required to pay with Zakelijk op rekening. Please fill in the company name in your billing details.', 'wc-buckaroo-bpe-gateway'),
                'error'
            );
        }

        if (! $this->isValidCocNumber($this->request->input('buckaroo-zakelijkoprekening-company-coc-registration'))) {
            wc_add_notice(
                __('Please enter a valid Chamber of Commerce (KvK) number.', 'wc-buckaroo-bpe-gateway'),
                'error'
            );
        }

        parent::validate_fields();
    }

    /**
     * Process the checkout: reserve the funds with an Authorize call.
     *
     * @param  int  $order_id
     * @return array|void
     */
    public function process_payment($order_id)
    {
        $processor = $this->newPaymentProcessorInstance($order_id);

        try {
            $response = $this->runBuckarooAction($processor, 'authorize');
        } catch (\Throwable $e) {
            Logger::log(__METHOD__ . '|error|', $e->getMessage());
            wc_add_notice(
                __('Could not start the payment. Please try again or choose another payment method.', 'wc-buckaroo-bpe-gateway'),
                'error'
            );

            return ['result' => 'failure'];
        }

        $result = (new ReturnProcessor($response->toArray(), false))->handle($this);

        if (isset($result['result']) && $result['result'] === 'success') {
            update_post_meta($order_id, '_wc_order_authorized', 'yes');
            $this->set_order_capture($order_id, 'ZakelijkOpRekening');
        }

        return $result;
    }

    /**
     * Capture (part of) a previously authorized order.
     *
     * @param  int  $order_id
     * @return array|array[]|false|\WP_Error
     */
    public function process_capture($order_id)
    {
        if (! $this->capturable || ! $this->canShowCaptureForm($order_id)) {
            return $this->create_capture_error(__('This order cannot be captured', 'wc-buckaroo-bpe-gateway'));
        }

        if ($order_id === null || ! is_numeric($order_id)) {
            return $this->create_capture_error(__('A valid order number is required', 'wc-buckaroo-bpe-gateway'));
        }

        $capture_amount = $this->request->input('capture_amount');
        if ($capture_amount === null || ! is_scalar($capture_amount)) {
            return $this->create_capture_error(__('A valid capture amount is required', 'wc-buckaroo-bpe-gateway'));
        }

        $order = Helper::findOrder($order_id);
        $processor = $this->newPaymentProcessorInstance($order);

        $response = $this->runBuckarooAction($processor, 'capture', [
            'amountDebit' => $capture_amount,
            'originalTransactionKey' => $order->get_transaction_id(),
        ]);

        return (new CaptureAction())->handle($response, $order, $this->currency);
    }

    /**
     * Run an Authorize or Capture action through our In3-based payment method,
     * leaving the bundled SDK (which lacks these methods for In3) untouched.
     *
     * @param  array<string, mixed>  $extra
     */
    private function runBuckarooAction(AbstractProcessor $processor, string $action, array $extra = []): TransactionResponse
    {
        $client = new BuckarooClient($this->getMode());
        $method = new ZakelijkOpRekeningPaymentMethod($client->client(), $this->getServiceCode($processor));

        $payload = (new PayloadService(array_merge($processor->getBody(), $extra)))->toArray();
        $method->setPayload($payload);

        Logger::log(__METHOD__ . '|1|', [$action, $payload]);

        return $method->{$action}();
    }

    /**
     * Show the capture meta box only once the order has been authorized.
     */
    public function canShowCaptureForm($order): bool
    {
        $order = Helper::resolveOrder($order);

        if (! $order instanceof WC_Order) {
            return false;
        }

        return get_post_meta($order->get_id(), '_wc_order_authorized', true) === 'yes';
    }

    /**
     * Restrict availability to NL B2B orders within the scheme amount limits.
     */
    public function isAvailable(float $cartTotal)
    {
        // Keep the method visible while the cart is still empty/zero (e.g. the
        // amount is not yet known); only hide it once an out-of-range total is
        // known. The B2B company + KvK requirements are enforced in
        // validate_fields() so the method can still be selected and filled in.
        if ($cartTotal > 0 && ($cartTotal < self::MIN_AMOUNT || $cartTotal > self::MAX_AMOUNT)) {
            return false;
        }

        $customer = (function_exists('WC') && WC()) ? WC()->customer : null;
        if ($customer === null) {
            return true;
        }

        // Netherlands only. Hide solely when a country is known and it is not
        // NL; an unknown/empty country keeps the method visible.
        $country = $customer->get_billing_country() ?: $customer->get_shipping_country();

        return $country === '' || $country === 'NL';
    }

    /**
     * Resolve the billing company name from the submitted request, falling
     * back to the WooCommerce customer session. This keeps validation correct
     * across the classic checkout (posted field) and any context where the
     * company is only available on the customer object.
     */
    private function getCheckoutCompany(): string
    {
        // Our own company field (collected by this payment method) takes
        // precedence, so the method works even when the WooCommerce billing
        // "Company" field is hidden in the checkout.
        $own = $this->request->input('buckaroo-zakelijkoprekening-company');
        if (is_string($own) && trim($own) !== '') {
            return trim($own);
        }

        $company = $this->request->input('billing_company');
        if (is_string($company) && trim($company) !== '') {
            return trim($company);
        }

        $customer = (function_exists('WC') && WC()) ? WC()->customer : null;
        if ($customer) {
            $company = $customer->get_billing_company();
            if (is_string($company) && trim($company) !== '') {
                return trim($company);
            }
        }

        return '';
    }

    /**
     * A Dutch Chamber of Commerce (KvK) number is 8 digits.
     *
     * @param  mixed  $coc
     */
    private function isValidCocNumber($coc): bool
    {
        if (! is_scalar($coc)) {
            return false;
        }

        return (bool) preg_match('/^\d{8}$/', trim((string) $coc));
    }
}
