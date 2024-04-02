<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Locator;

use WC_Buckaroo\Dependencies\GrumPHP\Exception\ExecutableNotFoundException;
use WC_Buckaroo\Dependencies\GrumPHP\Util\Paths;
use WC_Buckaroo\Dependencies\Symfony\Component\Process\ExecutableFinder;

class ExternalCommand
{
    /**
     * @var list<string>
     */
    private $suffixes = ['', '.phar'];

    /**
     * @var string
     */
    protected $binDir;

    /**
     * @var ExecutableFinder
     */
    protected $executableFinder;

    public function __construct(string $binDir, ExecutableFinder $executableFinder)
    {
        $this->binDir = rtrim($binDir, '/\\');
        $this->executableFinder = $executableFinder;
    }

    public static function loadWithPaths(Paths $paths, ExecutableFinder $executableFinder): self
    {
        return new self(
            $paths->getBinDir(),
            $executableFinder
        );
    }

    public function locate(string $command): string
    {
        foreach ($this->suffixes as $suffix) {
            $cmdName = $command . $suffix;
            // Search executable:
            $executable = $this->executableFinder->find($cmdName, null, [$this->binDir]);

            if ($executable) {
                return $executable;
            }
        }

        throw ExecutableNotFoundException::forCommand($command);
    }
}
