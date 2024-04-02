<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Event;

final class TaskEvents
{
    const TASK_RUN = 'grumphp.task.run';
    const TASK_COMPLETE = 'grumphp.task.complete';
    const TASK_FAILED = 'grumphp.task.failed';
    const TASK_SKIPPED = 'grumphp.task.skipped';
}
