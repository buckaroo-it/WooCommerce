<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Exception;

use WC_Buckaroo\Dependencies\GrumPHP\Util\Platform;
use WC_Buckaroo\Dependencies\Symfony\Component\Process\Process;

class PlatformException extends RuntimeException
{
    public static function commandLineStringLimit(Process $process): self
    {
        return new self(sprintf(
            'The Windows maximum amount of %s input characters exceeded while running process: %s ...',
            Platform::WINDOWS_COMMANDLINE_STRING_LIMITATION,
            substr($process->getCommandLine(), 0, 75)
        ));
    }
}
