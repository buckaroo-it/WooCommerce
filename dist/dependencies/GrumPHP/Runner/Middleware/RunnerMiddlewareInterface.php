<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Runner\Middleware;

use WC_Buckaroo\Dependencies\GrumPHP\Collection\TaskResultCollection;
use WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskRunnerContext;

interface RunnerMiddlewareInterface
{
    /**
     * @param callable(TaskRunnerContext $info): TaskResultCollection $next
     */
    public function handle(TaskRunnerContext $context, callable $next): TaskResultCollection;
}
