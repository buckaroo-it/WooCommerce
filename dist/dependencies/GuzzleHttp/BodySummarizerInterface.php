<?php

namespace WC_Buckaroo\Dependencies\GuzzleHttp;

use WC_Buckaroo\Dependencies\Psr\Http\Message\MessageInterface;

interface BodySummarizerInterface
{
    /**
     * Returns a summarized message body.
     */
    public function summarize(MessageInterface $message): ?string;
}
