<?php

namespace Buckaroo\Woocommerce\ResponseParser;

use Buckaroo\Woocommerce\Constraints\BuckarooTransactionStatus;
use Buckaroo\Woocommerce\Order\OrderMeta;
use BuckarooDeps\Buckaroo\Adapter\Contracts\Result;
use BuckarooDeps\Buckaroo\Adapter\Status;
use WC_Order;

/**
 * A UPG answer read through the plugin's parser contract, so the return and push processors keep their flow.
 *
 * UPG has no BPE status codes: the order logic runs on the core status, and the code-based checks use the
 * neutral status instead. Fields UPG does not have are null.
 */
class UpgResponseParser extends ResponseParser
{
    /** Order meta: the UPG sales this order created, one per payment attempt. */
    private const SALES_META = '_buckaroo_upg_sales';

    private Result $result;

    private string $paymentMethod;

    private bool $test;

    private bool $followRedirect;

    /**
     * @param  string  $paymentMethod  the gateway's service code, for example "wero"
     * @param  bool  $test  whether the payment was made in test mode; UPG keys belong to one mode
     * @param  bool  $followRedirect  true only for the answer to a payment. UPG keeps the redirect link on a sale
     *                                that is still pending, so a return page or webhook that followed it would send
     *                                the consumer back to the wallet (or, in the sandbox, back to the return page).
     */
    public function __construct(Result $result, string $paymentMethod, bool $test, bool $followRedirect = false)
    {
        parent::__construct([]);
        $this->result = $result;
        $this->paymentMethod = $paymentMethod;
        $this->test = $test;
        $this->followRedirect = $followRedirect;
        $this->set('coreStatus', self::coreStatus($result->status()));
    }

    public static function rememberSale(WC_Order $order, string $reference): void
    {
        OrderMeta::update($order, self::SALES_META, array_merge(self::sales($order), [$reference]));
    }

    /**
     * The order a UPG result may change: the order's current payment attempt, or an earlier attempt of that order
     * that is paid. An earlier attempt can still report later: when it failed or expired it must not fail the order,
     * but when it is paid the money is received, and it completes the order as a BPE push does. A sale the order did
     * not create never changes it: one UPG account can serve several shops, and order ids repeat between them.
     */
    public static function orderToUpdate(Result $result): ?WC_Order
    {
        $order = wc_get_order($result->additionalParameters()['real_order_id'] ?? 0);
        if (! $order instanceof WC_Order) {
            return null;
        }

        if ($order->get_transaction_id() === $result->reference()) {
            return $order;
        }

        return $result->status() === Status::Success && in_array($result->reference(), self::sales($order), true) ? $order : null;
    }

    private static function sales(WC_Order $order): array
    {
        $sales = OrderMeta::get($order, self::SALES_META);

        return is_array($sales) ? $sales : [];
    }

    private static function coreStatus(Status $status): string
    {
        switch ($status) {
            case Status::Success:
                return BuckarooTransactionStatus::STATUS_COMPLETED;
            case Status::Pending:
                return BuckarooTransactionStatus::STATUS_ON_HOLD;
            case Status::Cancelled:
            case Status::Expired:
                return BuckarooTransactionStatus::STATUS_CANCELLED;
            default:
                return BuckarooTransactionStatus::STATUS_FAILED;
        }
    }

    /** As for BPE: paid and pending both continue to the thank-you page. */
    public function isSuccess(): bool
    {
        return in_array($this->result->status(), [Status::Success, Status::Pending], true);
    }

    public function isFailed(): bool
    {
        return $this->result->status() === Status::Failed;
    }

    public function isPendingProcessing(): bool
    {
        return $this->result->status() === Status::Pending;
    }

    public function isOnHold(): bool
    {
        return $this->result->status() === Status::Pending;
    }

    public function isCanceled(): bool
    {
        return in_array($this->result->status(), [Status::Cancelled, Status::Expired], true);
    }

    public function getAmountDebit(): ?float
    {
        return $this->formatAmount($this->result->amount());
    }

    public function getAmountCredit(): ?float
    {
        return null;
    }

    public function getAmount(): ?float
    {
        return $this->getAmountDebit();
    }

    public function getCurrency(): ?string
    {
        return $this->result->currency();
    }

    public function getCustomerName(): ?string
    {
        return null;
    }

    public function getDescription()
    {
        return null;
    }

    public function getInvoice(): ?string
    {
        return $this->getAdditionalInformation('invoice');
    }

    public function getOrderNumber(): ?string
    {
        return null;
    }

    public function getMutationType()
    {
        return null;
    }

    public function getStatusCode(): ?int
    {
        return null;
    }

    public function getSubStatusCode()
    {
        return null;
    }

    public function getSubCodeMessage(): ?string
    {
        return $this->result->message() ?? '';
    }

    public function getTransactionMethod()
    {
        return $this->paymentMethod;
    }

    public function getTransactionType()
    {
        return null;
    }

    public function getTransactionKey(): ?string
    {
        return $this->result->reference();
    }

    public function getActionCode(): ?string
    {
        return null;
    }

    public function getDataRequest(): ?string
    {
        return null;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function getRelatedTransactionPartialPayment(): ?string
    {
        return null;
    }

    public function getAdditionalInformation($propertyName)
    {
        return $this->result->additionalParameters()[$propertyName] ?? null;
    }

    public function getRefundParentKey(): ?string
    {
        return null;
    }

    public function isRefund()
    {
        return false;
    }

    public function getPayerHash(): ?string
    {
        return null;
    }

    public function getPaymentKey(): ?string
    {
        return null;
    }

    public function isTest(): bool
    {
        return $this->test;
    }

    public function hasRedirect(): bool
    {
        return $this->followRedirect && $this->result->redirectUrl() !== null;
    }

    public function getRedirectUrl(): string
    {
        return $this->hasRedirect() ? (string) $this->result->redirectUrl() : '';
    }

    public function getService($name)
    {
        return null;
    }

    public function getServiceParameter($name, $service = null)
    {
        return null;
    }

    public function getRealOrderId()
    {
        return $this->getAdditionalInformation('real_order_id');
    }
}
