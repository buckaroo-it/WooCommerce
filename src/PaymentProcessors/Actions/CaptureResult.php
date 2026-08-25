<?php

namespace Buckaroo\Woocommerce\PaymentProcessors\Actions;

class CaptureResult
{
    public const SUCCEEDED = 'succeeded';

    public const PENDING = 'pending';

    public const IN_PROGRESS = 'in_progress';

    public const FAILED = 'failed';

    public const UNKNOWN = 'unknown';

    public const SKIPPED = 'skipped';

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

    public static function unknown(string $message): self
    {
        return new self(self::UNKNOWN, $message);
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

    public function getResponseData(): array
    {
        return $this->responseData;
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
