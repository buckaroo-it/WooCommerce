<?php

namespace WC_Buckaroo\Dependencies\Amp\Parallel\Context;

use WC_Buckaroo\Dependencies\Amp\Parallel\Sync\Channel;
use WC_Buckaroo\Dependencies\Amp\Promise;

interface Context extends Channel
{
    /**
     * @return bool
     */
    public function isRunning(): bool;

    /**
     * Starts the execution context.
     *
     * @return Promise<null> Resolved once the context has started.
     */
    public function start(): Promise;

    /**
     * Immediately kills the context.
     */
    public function kill();

    /**
     * @return \WC_Buckaroo\Dependencies\Amp\Promise<mixed> Resolves with the returned from the context.
     *
     * @throws \WC_Buckaroo\Dependencies\Amp\Parallel\Context\ContextException If the context dies unexpectedly.
     * @throws \WC_Buckaroo\Dependencies\Amp\Parallel\Sync\PanicError If the context throws an uncaught exception.
     */
    public function join(): Promise;
}
