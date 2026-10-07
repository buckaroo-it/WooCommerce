<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Services\BuckarooClient;
use PHPUnit\Framework\TestCase;

class Test_BuckarooClientReplyValidation extends TestCase
{
    public function test_malformed_reply_data_is_reported_as_invalid()
    {
        $client = new BuckarooClient('test', 'website-key', 'secret-key');

        $this->assertFalse(
            $client->isReplyHandlerValid([
                'brq_statuscode' => ['190'],
                'brq_signature' => 'signature',
            ])
        );
    }

    public function test_reply_without_signature_is_reported_as_invalid()
    {
        $client = new BuckarooClient('test', 'website-key', 'secret-key');

        $this->assertFalse(
            $client->isReplyHandlerValid([
                'brq_statuscode' => '190',
            ])
        );
    }
}
