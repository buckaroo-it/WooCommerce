<?php

namespace WC_Buckaroo\Dependencies\Buckaroo\Handlers\Logging\Observers;

use WC_Buckaroo\Dependencies\Buckaroo\Handlers\Logging\Observer;

class ErrorReporter implements Observer
{
    private array $reportables = [
        'error',
        'critical',
        'emergency',
    ];

    public function handle(string $method, string $message, array $context = [])
    {
        if (in_array($method, $this->reportables))
        {
            //print("Fire off message to mail/report server/slack");
        }

        return $this;
    }
}
