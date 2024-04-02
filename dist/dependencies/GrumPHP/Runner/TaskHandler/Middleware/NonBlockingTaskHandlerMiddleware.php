<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskHandler\Middleware;

use function WC_Buckaroo\Dependencies\Amp\call;
use WC_Buckaroo\Dependencies\Amp\Promise;
use WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskResult;
use WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskResultInterface;
use WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskRunnerContext;
use WC_Buckaroo\Dependencies\GrumPHP\Task\TaskInterface;

class NonBlockingTaskHandlerMiddleware implements TaskHandlerMiddlewareInterface
{
    public function handle(
        TaskInterface $task,
        TaskRunnerContext $runnerContext,
        callable $next
    ): Promise {
        return call(
            /**
             * @return \Generator<mixed, Promise<TaskResultInterface>, mixed, TaskResultInterface>
             */
            static function () use ($task, $runnerContext, $next): \Generator {
                /** @var TaskResultInterface $result */
                $result = yield $next($task, $runnerContext);

                if ($result->isPassed() || $result->isSkipped() || $task->getConfig()->getMetadata()->isBlocking()) {
                    return $result;
                }

                return TaskResult::createNonBlockingFailed(
                    $result->getTask(),
                    $result->getContext(),
                    $result->getMessage()
                );
            }
        );
    }
}
