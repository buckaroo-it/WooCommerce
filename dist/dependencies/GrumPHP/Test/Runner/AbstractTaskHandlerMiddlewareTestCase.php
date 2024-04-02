<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Test\Runner;

use WC_Buckaroo\Dependencies\Amp\Failure;
use WC_Buckaroo\Dependencies\Amp\Promise;
use WC_Buckaroo\Dependencies\Amp\Success;
use WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskResultInterface;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Config\Metadata;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Config\TaskConfig;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Context\ContextInterface;
use WC_Buckaroo\Dependencies\GrumPHP\Task\TaskInterface;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use function WC_Buckaroo\Dependencies\Amp\Promise\wait;

abstract class AbstractTaskHandlerMiddlewareTestCase extends AbstractMiddlewareTestCase
{
    protected function createNextResultCallback(TaskResultInterface $taskResult): callable
    {
        return static function () use ($taskResult) {
            return new Success($taskResult);
        };
    }

    protected function createExceptionCallback(\Throwable $exception): callable
    {
        return static function () use ($exception) {
            return new Failure($exception);
        };
    }

    protected function resolve(Promise $promise): TaskResultInterface
    {
        return wait($promise);
    }

    protected function mockTaskRun(string $name, callable $runWillDo): TaskInterface
    {
        /** @var ObjectProphecy|TaskInterface $task */
        $task = $this->prophesize(TaskInterface::class);
        $task->getConfig()->willReturn(new TaskConfig($name, [], new Metadata([])));
        $task->run(Argument::type(ContextInterface::class))->will($runWillDo);

        return $task->reveal();
    }
}
