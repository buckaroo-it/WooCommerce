<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Process;

use WC_Buckaroo\Dependencies\GrumPHP\Collection\ProcessArgumentsCollection;
use WC_Buckaroo\Dependencies\GrumPHP\Configuration\Model\ProcessConfig;
use WC_Buckaroo\Dependencies\GrumPHP\Exception\PlatformException;
use WC_Buckaroo\Dependencies\GrumPHP\IO\IOInterface;
use WC_Buckaroo\Dependencies\GrumPHP\Locator\ExternalCommand;
use WC_Buckaroo\Dependencies\GrumPHP\Util\Platform;
use WC_Buckaroo\Dependencies\Symfony\Component\Process\Process;

class ProcessBuilder
{
    /**
     * @var ExternalCommand
     */
    private $externalCommandLocator;

    /**
     * @var IOInterface
     */
    private $io;

    /**
     * @var ProcessConfig
     */
    private $config;

    public function __construct(ExternalCommand $externalCommandLocator, IOInterface $io, ProcessConfig $config)
    {
        $this->externalCommandLocator = $externalCommandLocator;
        $this->io = $io;
        $this->config = $config;
    }

    /**
     * @param callable(string); string>|null $pathManipulator
     */
    public function createArgumentsForCommand(
        string $command,
        callable $pathManipulator = null
    ): ProcessArgumentsCollection {
        $executable = $this->externalCommandLocator->locate($command);
        $manipulatedExecutable = $pathManipulator ? $pathManipulator($executable) : $executable;

        return ProcessArgumentsCollection::forExecutable((string) $manipulatedExecutable);
    }

    /**
     * @throws PlatformException
     */
    public function buildProcess(ProcessArgumentsCollection $arguments): Process
    {
        $process = ProcessFactory::fromArguments($arguments);
        $process->setTimeout($this->config->getTimeout());

        $this->logProcessInVerboseMode($process);
        $this->guardWindowsCmdMaxInputStringLimitation($process);

        return $process;
    }

    /**
     * @throws PlatformException
     */
    private function guardWindowsCmdMaxInputStringLimitation(Process $process): void
    {
        if (!Platform::isWindows()) {
            return;
        }

        if (\strlen($process->getCommandLine()) <= Platform::WINDOWS_COMMANDLINE_STRING_LIMITATION) {
            return;
        }

        throw PlatformException::commandLineStringLimit($process);
    }

    private function logProcessInVerboseMode(Process $process): void
    {
        if ($this->io->isVeryVerbose()) {
            $this->io->write([PHP_EOL.'Command: '.$process->getCommandLine()], true);
        }
    }
}
