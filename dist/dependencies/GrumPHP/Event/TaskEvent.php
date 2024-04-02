<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Event;

use WC_Buckaroo\Dependencies\GrumPHP\Task\Context\ContextInterface;
use WC_Buckaroo\Dependencies\GrumPHP\Task\TaskInterface;

class TaskEvent extends Event
{
    /**
     * @var TaskInterface
     */
    private $task;

    /**
     * @var ContextInterface
     */
    private $context;

    public function __construct(TaskInterface $task, ContextInterface $context)
    {
        $this->task = $task;
        $this->context = $context;
    }

    public function getTask(): TaskInterface
    {
        return $this->task;
    }

    public function getContext(): ContextInterface
    {
        return $this->context;
    }
}
