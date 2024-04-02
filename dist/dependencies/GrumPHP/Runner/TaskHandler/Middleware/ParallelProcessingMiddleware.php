<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskHandler\Middleware;

use WC_Buckaroo\Dependencies\GrumPHP\Exception\ParallelException;
use WC_Buckaroo\Dependencies\GrumPHP\IO\IOInterface;
use function WC_Buckaroo\Dependencies\Amp\call;
use function WC_Buckaroo\Dependencies\Amp\ParallelFunctions\parallel;
use WC_Buckaroo\Dependencies\Amp\Promise;
use function WC_Buckaroo\Dependencies\Amp\Promise\wait;
use WC_Buckaroo\Dependencies\GrumPHP\Configuration\Model\ParallelConfig;
use WC_Buckaroo\Dependencies\GrumPHP\Runner\Parallel\PoolFactory;
use WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskResult;
use WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskResultInterface;
use WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskRunnerContext;
use WC_Buckaroo\Dependencies\GrumPHP\Task\TaskInterface;

class ParallelProcessingMiddleware implements TaskHandlerMiddlewareInterface
{
    /**
     * @var ParallelConfig
     */
    private $config;

    /**
     * @var PoolFactory
     */
    private $poolFactory;

    /**
     * @var IOInterface
     */
    private $IO;

    public function __construct(ParallelConfig $config, PoolFactory $poolFactory, IOInterface $IO)
    {
        $this->poolFactory = $poolFactory;
        $this->config = $config;
        $this->IO = $IO;
    }

    public function handle(TaskInterface $task, TaskRunnerContext $runnerContext, callable $next): Promise
    {
        if (!$this->config->isEnabled()) {
            return $next($task, $runnerContext);
        }

        $currentEnv = $_ENV;

        /**
         * This method creates a callable that can be used to enqueue to run the task in parallel.
         * The result is wrapped in a serializable closure
         * to make sure all information inside the task can be serialized.
         * This implies that the result of the parallel command is another callable that will return the task result.
         *
         * The factory is wrapped in another close to make sure the error handling picks up the factory exceptions.
         *
         * @var callable(): Promise<TaskResultInterface> $enqueueParallelTask
         */
        $enqueueParallelTask = function () use ($task, $runnerContext, $next, $currentEnv): Promise {
            return parallel(
                static function (array $parentEnv) use ($task, $runnerContext, $next): TaskResultInterface {
                    $_ENV = array_merge($parentEnv, $_ENV);
                    /** @var TaskResultInterface $result */
                    $result = wait($next($task, $runnerContext));

                    return $result;
                },
                $this->poolFactory->create()
            )($currentEnv);
        };

        return call(
            /**
             * @return \Generator<mixed, Promise<TaskResultInterface>, mixed, TaskResultInterface>
             */
            function () use ($enqueueParallelTask, $task, $runnerContext): \Generator {
                try {
                    $result = yield $enqueueParallelTask();
                } catch (\Throwable $error) {
                    return TaskResult::createFailed(
                        $task,
                        $runnerContext->getTaskContext(),
                        $this->wrapException($error)->getMessage()
                    );
                }

                return $result;
            }
        );
    }

    private function wrapException(\Throwable $error): ParallelException
    {
        return $this->IO->isVerbose()
            ? ParallelException::fromVerboseThrowable($error)
            : ParallelException::fromThrowable($error);
    }
}
