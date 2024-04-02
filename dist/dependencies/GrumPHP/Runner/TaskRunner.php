<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Runner;

use WC_Buckaroo\Dependencies\GrumPHP\Collection\TaskResultCollection;
use WC_Buckaroo\Dependencies\GrumPHP\Collection\TasksCollection;

class TaskRunner
{
    /**
     * @var TasksCollection
     */
    private $tasks;

    /**
     * @var MiddlewareStack
     */
    private $middleware;

    public function __construct(TasksCollection $tasks, MiddlewareStack $middleware)
    {
        $this->tasks = $tasks;
        $this->middleware = $middleware;
    }

    public function run(TaskRunnerContext $runnerContext): TaskResultCollection
    {
        return $this->middleware->handle(
            $runnerContext->withTasks($this->tasks)
        );
    }
}
