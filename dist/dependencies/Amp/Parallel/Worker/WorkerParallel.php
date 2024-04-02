<?php

namespace WC_Buckaroo\Dependencies\Amp\Parallel\Worker;

use WC_Buckaroo\Dependencies\Amp\Parallel\Context\Parallel;

/**
 * A worker parallel extension thread that executes task objects.
 */
final class WorkerParallel extends TaskWorker
{
    const SCRIPT_PATH = __DIR__ . "/Internal/worker-process.php";

    /**
     * @param string $envClassName Name of class implementing \WC_Buckaroo\Dependencies\Amp\Parallel\Worker\Environment to instigate.
     *     Defaults to \WC_Buckaroo\Dependencies\Amp\Parallel\Worker\BasicEnvironment.
     * @param string|null Path to custom bootstrap file.
     *
     * @throws \Error If the PHP binary path given cannot be found or is not executable.
     */
    public function __construct(string $envClassName = BasicEnvironment::class, string $bootstrapPath = null)
    {
        $script = [
            self::SCRIPT_PATH,
            $envClassName,
        ];

        if ($bootstrapPath !== null) {
            $script[] = $bootstrapPath;
        }

        parent::__construct(new Parallel($script));
    }
}
