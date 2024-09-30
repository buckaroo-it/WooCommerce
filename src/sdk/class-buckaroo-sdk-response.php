<?php

namespace WC_Buckaroo\WooCommerce\SDK;

use Buckaroo\Transaction\Response\TransactionResponse;

class Buckaroo_Sdk_Response
{
    protected TransactionResponse $response;

    public function __construct(TransactionResponse $response)
    {
        $this->response = $response;
    }

    /**
     * @return bool
     */
    public function is_success(): bool
    {
        return $this->response->isSuccess();
    }

    /**
     * @return bool
     */
    public function is_failed(): bool
    {
        return $this->response->isFailed();
    }

    /**
     * @return bool
     */
    public function is_canceled(): bool
    {
        return $this->response->isCanceled();
    }

    /**
     * @return bool
     */
    public function is_awaiting_consumer(): bool
    {
        return $this->response->isAwaitingConsumer();
    }

    /**
     * @return bool
     */
    public function is_pending_processing(): bool
    {
        return $this->response->isPendingProcessing();
    }

    /**
     * @return bool
     */
    public function is_waiting_on_user_input(): bool
    {
        return $this->response->isWaitingOnUserInput();
    }

    /**
     * @return bool
     */
    public function is_rejected(): bool
    {
        return $this->response->isRejected();
    }

    /**
     * @return bool
     */
    public function is_validation_failure(): bool
    {
        return $this->response->isValidationFailure();
    }

    /**
     * @return boolean
     */
    public function has_redirect(): bool
    {
        $reqAction = $this->response->get('RequiredAction');

        return is_array($reqAction) &&
            !empty($reqAction['RedirectURL']) &&
            !empty($reqAction['Name']) &&
            $reqAction['Name'] == 'Redirect';
    }

    /**
     * @return string
     */
    public function get_redirect_url(): string
    {
        $reqAction = $this->get('RequiredAction');
        if ($this->has_redirect() && is_array($reqAction)) {
            return $reqAction['RedirectURL'];
        }

        return '';
    }

    /**
     * Get the status code of the Buckaroo response
     *
     * @return int Buckaroo Response status
     */
    public function get_status_code(): ?int
    {
        return $this->response->getStatusCode();
    }

    public function is_test_mode(): bool
    {
        return $this->get('IsTest') === true;
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function get(string $key)
    {
        $data = $this->response->data();
        if (is_array($data) && isset($data[$key])) {
            return $data[$key];
        }
    }

    /**
     * @return array<mixed>
     */
    public function get_service_parameters(): array
    {
        return $this->response->getServiceParameters();
    }

    public function get_some_error(): string
    {
        return $this->response->getSomeError();
    }

    public function get_transaction_key(): string
    {
        return $this->response->getTransactionKey();
    }

    public function get_data(): array
    {
        return $this->response->data();
    }


    public function get_order_id(): ?int
    {
        $parameters = $this->response->getCustomParameters();
        $order_id = $parameters['real_order_id'] ?? null;
        if (is_scalar($order_id)) {
            return (int)$order_id;
        }
        return null;
    }

    public function get_refund_amount(): float
    {
        $amount = $this->response->get('AmountCredit');
        if (is_scalar($amount)) {
            return (float)$amount;
        }
        return 0;
    }

    public function get_captured_amount(): float
    {
        $amount = $this->response->get('AmountDebit');
        if (is_scalar($amount)) {
            return (float)$amount;
        }
        return 0;
    }
}
