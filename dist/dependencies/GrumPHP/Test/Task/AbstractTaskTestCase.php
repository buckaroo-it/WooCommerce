<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Test\Task;

use WC_Buckaroo\Dependencies\GrumPHP\Collection\FilesCollection;
use WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskResult;
use WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskResultInterface;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Config\EmptyTaskConfig;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Config\Metadata;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Config\TaskConfig;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Context\ContextInterface;
use WC_Buckaroo\Dependencies\GrumPHP\Task\TaskInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use WC_Buckaroo\Dependencies\Symfony\Component\Finder\SplFileInfo;
use WC_Buckaroo\Dependencies\Symfony\Component\OptionsResolver\Exception\ExceptionInterface;

abstract class AbstractTaskTestCase extends TestCase
{
    use ProphecyTrait;

    /**
     * @var TaskInterface
     */
    protected $task;

    abstract protected function provideTask(): TaskInterface;
    abstract public function provideConfigurableOptions(): iterable;
    abstract public function provideRunContexts(): iterable;
    abstract public function provideFailsOnStuff(): iterable;
    abstract public function providePassesOnStuff(): iterable;
    abstract public function provideSkipsOnStuff(): iterable;

    protected function setUp(): void
    {
        parent::setUp();
        $this->task = $this->provideTask();
    }

    /**
     * @test
     * @dataProvider provideConfigurableOptions
     */
    public function it_contains_configurable_options(array $input, ?array $output): void
    {
        if (!$output) {
            self::expectException(ExceptionInterface::class);
        }

        $resolver = $this->task::getConfigurableOptions();

        self::assertSame(
            $output,
            $resolver->resolve($input)
        );
    }

    /**
     * @test
     * @dataProvider provideRunContexts
     */
    public function it_runs_in_specific_contexts(bool $expected, ContextInterface $context): void
    {
        self::assertSame(
            $expected,
            $this->task->canRunInContext($context)
        );
    }

    /**
     * @test
     */
    public function it_can_contain_config(): void
    {
        // All tasks require to return an empty config during construction.
        self::assertEquals(new EmptyTaskConfig(), $this->task->getConfig());

        $config = new TaskConfig('newConfig', [], new Metadata([]));

        // Validate if task has immutable withConfig method.
        $newVersion = $this->task->withConfig($config);
        self::assertNotSame($newVersion, $this->task);
        self::assertEquals(new EmptyTaskConfig(), $this->task->getConfig());
        self::assertSame($config, $newVersion->getConfig());
    }

    /**
     * @test
     * @dataProvider provideFailsOnStuff
     */
    public function it_fails_on_stuff(
        array $config,
        ContextInterface $context,
        callable $configurator,
        string $expectedErrorMessage,
        string $resultClass = TaskResult::class
    ): void {
        $task = $this->configureTask($config);
        \Closure::bind($configurator, $this)($task->getConfig()->getOptions(), $context);

        $result = $task->run($context);

        self::assertInstanceOf($resultClass, $result);
        self::assertSame(TaskResult::FAILED, $result->getResultCode());
        self::assertSame($task, $result->getTask());
        self::assertSame($context, $result->getContext());

        self::assertNotSame('', $expectedErrorMessage, 'Please specify (partial) expected error message!');
        self::assertStringContainsString($expectedErrorMessage, $result->getMessage());
    }

    /**
     * @test
     * @dataProvider providePassesOnStuff
     */
    public function it_passes_on_stuff(
        array $config,
        ContextInterface $context,
        callable $configurator
    ): void {
        $task = $this->configureTask($config);
        \Closure::bind($configurator, $this)($task->getConfig()->getOptions(), $context);

        $result = $task->run($context);
        self::assertInstanceOf(TaskResult::class, $result);
        self::assertSame(TaskResult::PASSED, $result->getResultCode());
        self::assertSame($task, $result->getTask());
        self::assertSame($context, $result->getContext());
        self::assertSame('', $result->getMessage());
    }

    /**
     * @test
     * @dataProvider provideSkipsOnStuff
     */
    public function it_skips_on_stuff(
        array $config,
        ContextInterface $context,
        callable $configurator
    ): void
    {
        $task = $this->configureTask($config);
        \Closure::bind($configurator, $this)($task->getConfig()->getOptions());

        $result = $task->run($context);
        self::assertInstanceOf(TaskResult::class, $result);
        self::assertSame(TaskResult::SKIPPED, $result->getResultCode());
        self::assertSame($task, $result->getTask());
        self::assertSame($context, $result->getContext());
        self::assertSame('', $result->getMessage());
    }

    protected function configureTask(array $options = []): TaskInterface
    {
        return $this->task->withConfig(
            new TaskConfig(
                '',
                $this->task::getConfigurableOptions()->resolve($options),
                new Metadata([])
            )
        );
    }

    protected function mockContext(string $class = ContextInterface::class, array $files = []): ContextInterface
    {
        /** @var ContextInterface|ObjectProphecy $context */
        $context = $this->prophesize($class);
        $context->getFiles()->willReturn(
            new FilesCollection(
                array_map(
                    static function ($file): SplFileInfo {
                        return $file instanceof SplFileInfo ? $file : new SplFileInfo($file, $file, $file);
                    },
                    $files
                )
            )
        );

        return $context->reveal();
    }
}
