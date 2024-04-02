<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Formatter;

use WC_Buckaroo\Dependencies\Symfony\Component\Process\Process;

interface ProcessFormatterInterface
{
    public function format(Process $process): string;
}
