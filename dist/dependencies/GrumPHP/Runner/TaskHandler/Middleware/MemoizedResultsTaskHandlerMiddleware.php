<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskHandler\Middleware;

use function WC_Buckaroo\Dependencies\Amp\call;
use WC_Buckaroo\Dependencies\Amp\Promise;
use WC_Buckaroo\Dependencies\GrumPHP\Runner\MemoizedTaskResultMap;
use WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskResult;
use WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskResultInterface;
use WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskRunnerContext;
use WC_Buckaroo\Dependencies\GrumPHP\Task\TaskInterface;

class MemoizedResultsTaskHandlerMiddleware implements TaskHandlerMiddlewareInterface
{
    /**
     * @var MemoizedTaskResultMap
     */
    private $resultMap;

    public function __construct(MemoizedTaskResultMap $resultMap)
    {
        $this->resultMap = $resultMap;
    }

    public function handle(TaskInterface $task, TaskRunnerContext $runnerContext, callable $next): Promise
    {
        return call(
            /**
             * @return \Generator<mixed, Promise<TaskResultInterface>, mixed, TaskResultInterface>
             */
            function () use ($task, $runnerContext, $next) : \Generator {
                try {
                    /** @var TaskResultInterface $result */
                    $result = yield $next($task, $runnerContext);
                } catch (\Throwable $error) {
                    $result = TaskResult::createFailed($task, $runnerContext->getTaskContext(), $error->getMessage());
                }

                $this->resultMap->onResult($result);

                return $result;
            }
        );
    }
}
