<?php

use WC_Buckaroo\Dependencies\Buckaroo\Resources\Constants\ResponseStatus;

class Buckaroo_Return_Payload
{
    private Buckaroo_Http_Request $request;

    public function __construct(Buckaroo_Http_Request $request)
    {
        $this->request = $request;
    }

    public function get_order_id(): ?int
    {
        $id = $this->request->request("ADD_real_order_id");
        if (!is_scalar($id)) {
            return null;
        }
        return (int)$id;
    }

    public function is_success(): bool
    {
        return in_array($this->get_status_code(), [
            ResponseStatus::BUCKAROO_STATUSCODE_SUCCESS,
            ResponseStatus::BUCKAROO_STATUSCODE_WAITING_ON_CONSUMER,
            ResponseStatus::BUCKAROO_STATUSCODE_PENDING_PROCESSING,
            ResponseStatus::BUCKAROO_STATUSCODE_WAITING_ON_USER_INPUT
        ]);
    }

    public function is_failed(): bool
    {
        return in_array($this->get_status_code(), [
            ResponseStatus::BUCKAROO_STATUSCODE_REJECTED,
            ResponseStatus::BUCKAROO_STATUSCODE_VALIDATION_FAILURE,
            ResponseStatus::BUCKAROO_STATUSCODE_FAILED
        ]);
    }

    public function is_cancelled(): bool
    {
        return in_array($this->get_status_code(), [
            ResponseStatus::BUCKAROO_STATUSCODE_CANCELLED_BY_USER,
            ResponseStatus::BUCKAROO_STATUSCODE_CANCELLED_BY_MERCHANT
        ]);
    }

    public function is_pending_processing(): bool
    {
        return $this->get_status_code() === ResponseStatus::BUCKAROO_STATUSCODE_PENDING_PROCESSING;
    }

    public function get_error_message(): string
    {
        $message = $this->request->request('brq_statusmessage');
        if (!is_string($message)) {
            return 'Unknown error message';
        }
        return $message;
    }
    private function get_status_code(): string
    {
        $status = $this->request->request("brq_statuscode");
        if (!is_scalar($status)) {
            return ResponseStatus::BUCKAROO_STATUSCODE_VALIDATION_FAILURE;
        }
        return (string)$status;
    }
}
