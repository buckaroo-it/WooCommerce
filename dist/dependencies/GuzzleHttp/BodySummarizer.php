<?php

namespace WC_Buckaroo\Dependencies\GuzzleHttp;

use WC_Buckaroo\Dependencies\Psr\Http\Message\MessageInterface;

final class BodySummarizer implements BodySummarizerInterface
{
    /**
     * @var int|null
     */
    private $truncateAt;

    public function __construct(int $truncateAt = null)
    {
        $this->truncateAt = $truncateAt;
    }

    /**
     * Returns a summarized message body.
     */
    public function summarize(MessageInterface $message): ?string
    {
        return $this->truncateAt === null
            ? \WC_Buckaroo\Dependencies\GuzzleHttp\Psr7\Message::bodySummary($message)
            : \WC_Buckaroo\Dependencies\GuzzleHttp\Psr7\Message::bodySummary($message, $this->truncateAt);
    }
}
