<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Task;

use WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskResultInterface;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Config\TaskConfigInterface;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Context\ContextInterface;
use WC_Buckaroo\Dependencies\Symfony\Component\OptionsResolver\OptionsResolver;

interface TaskInterface
{
    public static function getConfigurableOptions(): OptionsResolver;

    public function canRunInContext(ContextInterface $context): bool;

    public function run(ContextInterface $context): TaskResultInterface;

    public function getConfig(): TaskConfigInterface;

    public function withConfig(TaskConfigInterface $config): TaskInterface;
}
