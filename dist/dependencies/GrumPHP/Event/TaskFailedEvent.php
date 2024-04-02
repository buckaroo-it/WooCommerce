<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Event;

use Exception;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Context\ContextInterface;
use WC_Buckaroo\Dependencies\GrumPHP\Task\TaskInterface;

class TaskFailedEvent extends TaskEvent
{
    /**
     * @var Exception
     */
    private $exception;

    public function __construct(TaskInterface $task, ContextInterface $context, Exception $exception)
    {
        parent::__construct($task, $context);

        $this->exception = $exception;
    }

    public function getException(): Exception
    {
        return $this->exception;
    }
}
