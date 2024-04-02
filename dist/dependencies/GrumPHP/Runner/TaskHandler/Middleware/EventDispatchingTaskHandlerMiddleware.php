<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskHandler\Middleware;

use function WC_Buckaroo\Dependencies\Amp\call;
use WC_Buckaroo\Dependencies\Amp\Promise;
use WC_Buckaroo\Dependencies\GrumPHP\Event\Dispatcher\EventDispatcherInterface;
use WC_Buckaroo\Dependencies\GrumPHP\Event\TaskEvent;
use WC_Buckaroo\Dependencies\GrumPHP\Event\TaskEvents;
use WC_Buckaroo\Dependencies\GrumPHP\Event\TaskFailedEvent;
use WC_Buckaroo\Dependencies\GrumPHP\Exception\RuntimeException;
use WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskResultInterface;
use WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskRunnerContext;
use WC_Buckaroo\Dependencies\GrumPHP\Task\TaskInterface;

class EventDispatchingTaskHandlerMiddleware implements TaskHandlerMiddlewareInterface
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function handle(TaskInterface $task, TaskRunnerContext $runnerContext, callable $next): Promise
    {
        return call(
            /**
             * @return \Generator<mixed, Promise<TaskResultInterface>, mixed, TaskResultInterface>
             */
            function () use ($task, $runnerContext, $next): \Generator {
                $taskContext = $runnerContext->getTaskContext();
                $this->eventDispatcher->dispatch(new TaskEvent($task, $taskContext), TaskEvents::TASK_RUN);

                /** @var TaskResultInterface $result */
                $result = yield $next($task, $runnerContext);

                if ($result->isSkipped()) {
                    $this->eventDispatcher->dispatch(new TaskEvent($task, $taskContext), TaskEvents::TASK_SKIPPED);
                    return $result;
                }

                if ($result->hasFailed()) {
                    $e = new RuntimeException($result->getMessage());
                    $this->eventDispatcher->dispatch(
                        new TaskFailedEvent($task, $taskContext, $e),
                        TaskEvents::TASK_FAILED
                    );

                    return $result;
                }

                $this->eventDispatcher->dispatch(new TaskEvent($task, $taskContext), TaskEvents::TASK_COMPLETE);

                return $result;
            }
        );
    }
}
