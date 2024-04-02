<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskHandler\Middleware;

use function WC_Buckaroo\Dependencies\Amp\call;
use WC_Buckaroo\Dependencies\Amp\Promise;
use WC_Buckaroo\Dependencies\GrumPHP\Runner\Reporting\TaskResultsReporter;
use WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskResultInterface;
use WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskRunnerContext;
use WC_Buckaroo\Dependencies\GrumPHP\Task\TaskInterface;

class ReportingTaskHandlerMiddleware implements TaskHandlerMiddlewareInterface
{
    /**
     * @var TaskResultsReporter
     */
    private $reporter;

    public function __construct(TaskResultsReporter $reporter)
    {
        $this->reporter = $reporter;
    }

    public function handle(TaskInterface $task, TaskRunnerContext $runnerContext, callable $next): Promise
    {
        return call(
            /**
             * @return \Generator<mixed, Promise<TaskResultInterface>, mixed, TaskResultInterface>
             */
            function () use ($task, $runnerContext, $next) {
                /** @var TaskResultInterface $result */
                $result = yield $next($task, $runnerContext);

                $this->reporter->report($runnerContext);

                return $result;
            }
        );
    }
}
