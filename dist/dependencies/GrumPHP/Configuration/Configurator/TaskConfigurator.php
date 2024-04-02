<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Configuration\Configurator;

use WC_Buckaroo\Dependencies\GrumPHP\Task\Config\TaskConfigInterface;
use WC_Buckaroo\Dependencies\GrumPHP\Task\TaskInterface;

class TaskConfigurator
{
    public function __invoke(TaskInterface $task, TaskConfigInterface $config): TaskInterface
    {
        return $task->withConfig($config);
    }
}
