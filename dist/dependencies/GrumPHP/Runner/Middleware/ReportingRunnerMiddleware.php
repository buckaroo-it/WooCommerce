<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Runner\Middleware;

use WC_Buckaroo\Dependencies\GrumPHP\Collection\TaskResultCollection;
use WC_Buckaroo\Dependencies\GrumPHP\Runner\Reporting\RunnerReporter;
use WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskRunnerContext;

class ReportingRunnerMiddleware implements RunnerMiddlewareInterface
{
    /**
     * @var RunnerReporter
     */
    private $reporter;

    public function __construct(RunnerReporter $reporter)
    {
        $this->reporter = $reporter;
    }

    public function handle(TaskRunnerContext $context, callable $next): TaskResultCollection
    {
        $this->reporter->start($context);
        $results = $next($context);
        $this->reporter->finish($context, $results);

        return $results;
    }
}
