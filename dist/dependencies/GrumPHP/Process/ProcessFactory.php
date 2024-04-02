<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Process;

use WC_Buckaroo\Dependencies\GrumPHP\Collection\ProcessArgumentsCollection;
use WC_Buckaroo\Dependencies\Symfony\Component\Process\Process;

/**
 * @internal
 */
final class ProcessFactory
{
    public static function fromArguments(ProcessArgumentsCollection $arguments): Process
    {
        return new Process($arguments->getValues());
    }

    /**
     * @param array|string $arguments
     */
    public static function fromScalar($arguments): Process
    {
        return is_array($arguments) ? new Process($arguments) : Process::fromShellCommandline($arguments);
    }
}
