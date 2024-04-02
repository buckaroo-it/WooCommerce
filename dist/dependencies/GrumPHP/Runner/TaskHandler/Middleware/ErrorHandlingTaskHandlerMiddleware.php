<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskHandler\Middleware;

use WC_Buckaroo\Dependencies\Amp\Parallel\Worker\TaskFailureException;
use function WC_Buckaroo\Dependencies\Amp\call;
use WC_Buckaroo\Dependencies\Amp\Promise;
use WC_Buckaroo\Dependencies\GrumPHP\Exception\PlatformException;
use WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskResult;
use WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskResultInterface;
use WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskRunnerContext;
use WC_Buckaroo\Dependencies\GrumPHP\Task\TaskInterface;

class ErrorHandlingTaskHandlerMiddleware implements TaskHandlerMiddlewareInterface
{
    public function handle(
        TaskInterface $task,
        TaskRunnerContext $runnerContext,
        callable $next
    ): Promise {
        return call(
            static function () use ($task, $runnerContext): TaskResultInterface {
                $taskContext = $runnerContext->getTaskContext();
                try {
                    $result = $task->run($taskContext);
                } catch (PlatformException $e) {
                    return TaskResult::createSkipped($task, $taskContext);
                } catch (\Throwable $e) {
                    return TaskResult::createFailed($task, $taskContext, $e->getMessage());
                }

                return $result;
            }
        );
    }
}
