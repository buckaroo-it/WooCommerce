<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Test\Runner;

use WC_Buckaroo\Dependencies\GrumPHP\Collection\FilesCollection;
use WC_Buckaroo\Dependencies\GrumPHP\IO\IOInterface;
use WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskRunnerContext;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Config\Metadata;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Config\TaskConfig;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Context\ContextInterface;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Context\RunContext;
use WC_Buckaroo\Dependencies\GrumPHP\Task\TaskInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use WC_Buckaroo\Dependencies\Symfony\Component\Console\Style\StyleInterface;

class AbstractMiddlewareTestCase extends TestCase
{
    protected function createRunnerContext(): TaskRunnerContext
    {
        return new TaskRunnerContext(
            new RunContext(new FilesCollection())
        );
    }

    protected function createNextShouldNotBeCalledCallback(): callable
    {
        return static function () {
            throw new \RuntimeException('Expected next not to be called!');
        };
    }

    protected function mockIO(): IOInterface
    {
        /** @var ObjectProphecy|IOInterface $IO */
        $IO = $this->prophesize(IOInterface::class);
        $IO->isVerbose()->willReturn(false);
        $IO->style()->willReturn($this->prophesize(StyleInterface::class)->reveal());

        return $IO->reveal();
    }

    protected function mockTask(string $name, array $meta = []): TaskInterface
    {
        /** @var ObjectProphecy|TaskInterface $task */
        $task = $this->prophesize(TaskInterface::class);
        $task->getConfig()->willReturn(new TaskConfig($name, [], new Metadata($meta)));

        return $task->reveal();
    }
}
