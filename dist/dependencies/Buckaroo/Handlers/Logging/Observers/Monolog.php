<?php

namespace WC_Buckaroo\Dependencies\Buckaroo\Handlers\Logging\Observers;

use WC_Buckaroo\Dependencies\Buckaroo\Handlers\Logging\Observer;
use WC_Buckaroo\Dependencies\Monolog\Handler\StreamHandler;
use WC_Buckaroo\Dependencies\Monolog\Logger;
use WC_Buckaroo\Dependencies\Psr\Log\LoggerInterface;

class Monolog implements Observer
{
    protected LoggerInterface $log;

    public function __construct()
    {
        $this->log = new Logger('WC_Buckaroo\Dependencies\Buckaroo log');
        $this->log->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
    }

    public function handle(string $method, string $message, array $context = [])
    {
        $this->log->$method($message, $context);
    }
}
