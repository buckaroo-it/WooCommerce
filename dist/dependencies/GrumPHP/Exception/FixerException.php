<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Exception;

use WC_Buckaroo\Dependencies\GrumPHP\Formatter\RawProcessFormatter;
use WC_Buckaroo\Dependencies\Symfony\Component\Process\Process;

class FixerException extends RuntimeException
{
    public static function fromProcess(Process $process): self
    {
        return new self(
            'Error while fixing: '.
            $process->getCommandLine()
            . PHP_EOL
            . (new RawProcessFormatter())->format($process)
        );
    }
}
