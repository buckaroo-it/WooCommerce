<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Runner\Middleware;

use WC_Buckaroo\Dependencies\GrumPHP\Collection\TaskResultCollection;
use WC_Buckaroo\Dependencies\GrumPHP\Runner\Reporting\TaskResultsReporter;
use WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskRunnerContext;

class ReportingTasksSectionRunnerMiddleware implements RunnerMiddlewareInterface
{
    /**
     * @var TaskResultsReporter
     */
    private $reporter;

    public function __construct(TaskResultsReporter $reporter)
    {
        $this->reporter = $reporter;
    }

    public function handle(TaskRunnerContext $context, callable $next): TaskResultCollection
    {
        return $this->reporter->runInSection(
            /**
             * @return TaskResultCollection
             */
            function () use ($context, $next): TaskResultCollection {
                $this->reporter->report($context);

                return $next($context);
            }
        );
    }
}
