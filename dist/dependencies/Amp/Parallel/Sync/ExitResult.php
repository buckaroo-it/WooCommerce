<?php

namespace WC_Buckaroo\Dependencies\Amp\Parallel\Sync;

interface ExitResult
{
    /**
     * @return mixed Return value of the callable given to the execution context.
     *
     * @throws \WC_Buckaroo\Dependencies\Amp\Parallel\Sync\PanicError If the context exited with an uncaught exception.
     */
    public function getResult();
}
