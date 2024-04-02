<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Formatter;

use WC_Buckaroo\Dependencies\Symfony\Component\Process\Process;

class RawProcessFormatter implements ProcessFormatterInterface
{
    public function format(Process $process): string
    {
        $stdout = $process->getOutput();
        $stderr = $process->getErrorOutput();

        return trim($stdout.PHP_EOL.$stderr);
    }
}
