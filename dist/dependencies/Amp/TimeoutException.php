<?php

namespace WC_Buckaroo\Dependencies\Amp;

/**
 * Thrown if a promise doesn't resolve within a specified timeout.
 *
 * @see \WC_Buckaroo\Dependencies\Amp\Promise\timeout()
 */
class TimeoutException extends \Exception
{
    /**
     * @param string $message Exception message.
     */
    public function __construct(string $message = "Operation timed out")
    {
        parent::__construct($message);
    }
}
