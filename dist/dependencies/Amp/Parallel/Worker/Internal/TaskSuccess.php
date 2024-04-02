<?php

namespace WC_Buckaroo\Dependencies\Amp\Parallel\Worker\Internal;

use WC_Buckaroo\Dependencies\Amp\Failure;
use WC_Buckaroo\Dependencies\Amp\Parallel\Worker\Task;
use WC_Buckaroo\Dependencies\Amp\Promise;
use WC_Buckaroo\Dependencies\Amp\Success;

/** @internal */
final class TaskSuccess extends TaskResult
{
    /** @var mixed Result of task. */
    private $result;

    public function __construct(string $id, $result)
    {
        parent::__construct($id);
        $this->result = $result;
    }

    public function promise(): Promise
    {
        if ($this->result instanceof \__PHP_Incomplete_Class) {
            return new Failure(new \Error(\sprintf(
                "Class instances returned from %s::run() must be autoloadable by the Composer autoloader",
                Task::class
            )));
        }

        return new Success($this->result);
    }
}
