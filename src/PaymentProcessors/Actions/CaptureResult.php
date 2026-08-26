<?php

namespace Buckaroo\Woocommerce\PaymentProcessors\Actions;

use BuckarooDeps\Buckaroo\Resources\Constants\ResponseStatus;

class CaptureResult
{
    public const SUCCEEDED = 'succeeded';

    public const PENDING = 'pending';

    public const FAILED = 'failed';

    public const UNKNOWN = 'unknown';

    /**
     * Buckaroo status codes that are not final yet; the outcome arrives by push.
     */
    private const PENDING_STATUS_CODES = [
        ResponseStatus::BUCKAROO_STATUSCODE_WAITING_ON_USER_INPUT,
        ResponseStatus::BUCKAROO_STATUSCODE_PENDING_PROCESSING,
        ResponseStatus::BUCKAROO_STATUSCODE_WAITING_ON_CONSUMER,
        ResponseStatus::BUCKAROO_STATUSCODE_PAYMENT_ON_HOLD,
        ResponseStatus::BUCKAROO_STATUSCODE_PENDING_APPROVAL,
    ];

    private string $status;

    private string $message;

    private array $responseData;

    private ?string $transactionKey;

    private function __construct(string $status, string $message = '', array $responseData = [], ?string $transactionKey = null)
    {
        $this->status = $status;
        $this->message = $message;
        $this->responseData = $responseData;
        $this->transactionKey = $transactionKey;
    }

    public static function succeeded(array $responseData, string $transactionKey): self
    {
        return new self(self::SUCCEEDED, '', $responseData, $transactionKey);
    }

    public static function pending(array $responseData, ?string $transactionKey): self
    {
        return new self(self::PENDING, '', $responseData, $transactionKey);
    }

    public static function failed(string $message, ?string $transactionKey = null): self
    {
        return new self(self::FAILED, $message, [], $transactionKey);
    }

    public static function unknown(string $message, ?string $transactionKey = null): self
    {
        return new self(self::UNKNOWN, $message, [], $transactionKey);
    }

    public static function isPendingStatusCode($statusCode): bool
    {
        return is_numeric($statusCode) && in_array((string) (int) $statusCode, self::PENDING_STATUS_CODES, true);
    }

    public static function isSuccessStatusCode($statusCode): bool
    {
        return is_numeric($statusCode) && (int) $statusCode === (int) ResponseStatus::BUCKAROO_STATUSCODE_SUCCESS;
    }

    public function isSuccess(): bool
    {
        return $this->status === self::SUCCEEDED;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getTransactionKey(): ?string
    {
        return $this->transactionKey;
    }

    public function toAjaxResponse(): array
    {
        if ($this->isSuccess() || $this->status === self::PENDING) {
            return [
                'success' => true,
                'data' => $this->responseData,
            ];
        }

        return [
            'errors' => [
                'error_capture' => [
                    [$this->message],
                ],
            ],
        ];
    }
}
