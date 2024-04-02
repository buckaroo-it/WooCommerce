<?php

namespace WC_Buckaroo\Dependencies\Amp\Parallel\Worker;

/**
 * A runnable unit of execution.
 */
interface Task
{
    /**
     * Runs the task inside the caller's context.
     *
     * Does not have to be a coroutine, can also be a regular function returning a value.
     *
     * @param \WC_Buckaroo\Dependencies\Amp\Parallel\Worker\Environment
     *
     * @return mixed|\WC_Buckaroo\Dependencies\Amp\Promise|\Generator
     */
    public function run(Environment $environment);
}
