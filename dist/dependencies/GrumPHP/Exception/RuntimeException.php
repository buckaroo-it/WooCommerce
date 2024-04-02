<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Exception;

use Exception;
use WC_Buckaroo\Dependencies\GrumPHP\Task\TaskInterface;
use RuntimeException as BaseRuntimeException;

class RuntimeException extends BaseRuntimeException implements ExceptionInterface
{
    public static function fromAnyException(Exception $e): self
    {
        return new self($e->getMessage(), (int)$e->getCode(), $e);
    }

    public static function invalidTaskReturnType(TaskInterface $task): self
    {
        return new self(sprintf('The %s task did not return a TaskResult.', $task->getConfig()->getName()));
    }
}
