<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Runner\Middleware;

use WC_Buckaroo\Dependencies\GrumPHP\Collection\TaskResultCollection;
use WC_Buckaroo\Dependencies\GrumPHP\Fixer\FixerUpper;
use WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskRunnerContext;

class FixCodeMiddleware implements RunnerMiddlewareInterface
{
    /**
     * @var FixerUpper
     */
    private $fixerUpper;

    public function __construct(FixerUpper $fixerUpper)
    {
        $this->fixerUpper = $fixerUpper;
    }

    public function handle(TaskRunnerContext $context, callable $next): TaskResultCollection
    {
        /** @var TaskResultCollection $results */
        $results = $next($context);

        $this->fixerUpper->fix($results);

        return $results;
    }
}
