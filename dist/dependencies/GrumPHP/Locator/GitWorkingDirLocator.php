<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Locator;

use WC_Buckaroo\Dependencies\GrumPHP\Collection\ProcessArgumentsCollection;
use WC_Buckaroo\Dependencies\GrumPHP\Exception\RuntimeException;
use WC_Buckaroo\Dependencies\GrumPHP\Process\ProcessFactory;
use WC_Buckaroo\Dependencies\Symfony\Component\Process\ExecutableFinder;

class GitWorkingDirLocator
{
    /**
     * @var ExecutableFinder
     */
    private $executableFinder;

    public function __construct(ExecutableFinder $executableFinder)
    {
        $this->executableFinder = $executableFinder;
    }

    public function locate(): string
    {
        $arguments = ProcessArgumentsCollection::forExecutable((string) $this->executableFinder->find('git', 'git'));
        $arguments->add('rev-parse');
        $arguments->add('--show-toplevel');

        $process = ProcessFactory::fromArguments($arguments);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException(
                'The git directory could not be found. Did you initialize git? ('.$process->getErrorOutput().')'
            );
        }

        return trim($process->getOutput());
    }
}
