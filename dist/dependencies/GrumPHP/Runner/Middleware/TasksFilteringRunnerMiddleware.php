<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Runner\Middleware;

use WC_Buckaroo\Dependencies\GrumPHP\Collection\TaskResultCollection;
use WC_Buckaroo\Dependencies\GrumPHP\Collection\TasksCollection;
use WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskRunnerContext;

class TasksFilteringRunnerMiddleware implements RunnerMiddlewareInterface
{
    public function handle(TaskRunnerContext $context, callable $next): TaskResultCollection
    {
        return $next(
            $context->withTasks(
                (new TasksCollection($context->getTasks()->toArray()))
                    ->filterByContext($context->getTaskContext())
                    ->filterByTestSuite($context->getTestSuite())
                    ->filterByTaskNames($context->getTaskNames())
            )
        );
    }
}
