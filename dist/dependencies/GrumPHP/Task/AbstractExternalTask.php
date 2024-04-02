<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Task;

use WC_Buckaroo\Dependencies\GrumPHP\Formatter\ProcessFormatterInterface;
use WC_Buckaroo\Dependencies\GrumPHP\Process\ProcessBuilder;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Config\EmptyTaskConfig;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Config\TaskConfigInterface;

/**
 * @template-covariant Formatter extends ProcessFormatterInterface
 */
abstract class AbstractExternalTask implements TaskInterface
{
    /**
     * @var TaskConfigInterface
     */
    protected $config;

    /**
     * @var ProcessBuilder
     */
    protected $processBuilder;

    /**
     * @var Formatter
     */
    protected $formatter;

    /**
     * @param Formatter $formatter
     */
    public function __construct(ProcessBuilder $processBuilder, ProcessFormatterInterface $formatter)
    {
        $this->config = new EmptyTaskConfig();
        $this->processBuilder = $processBuilder;
        $this->formatter = $formatter;
    }

    public function getConfig(): TaskConfigInterface
    {
        return $this->config;
    }

    public function withConfig(TaskConfigInterface $config): TaskInterface
    {
        $new = clone $this;
        $new->config = $config;

        return $new;
    }
}
