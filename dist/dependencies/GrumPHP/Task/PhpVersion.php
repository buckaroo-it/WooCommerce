<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Task;

use WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskResult;
use WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskResultInterface;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Config\EmptyTaskConfig;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Config\TaskConfigInterface;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Context\ContextInterface;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Context\GitPreCommitContext;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Context\RunContext;
use WC_Buckaroo\Dependencies\GrumPHP\Util\PhpVersion as PhpVersionUtility;
use WC_Buckaroo\Dependencies\Symfony\Component\OptionsResolver\OptionsResolver;

class PhpVersion implements TaskInterface
{
    /**
     * @var TaskConfigInterface
     */
    private $config;

    /**
     * @var PhpVersionUtility
     */
    private $phpVersionUtility;

    public function __construct(PhpVersionUtility $phpVersionUtility)
    {
        $this->config = new EmptyTaskConfig();
        $this->phpVersionUtility = $phpVersionUtility;
    }

    public function canRunInContext(ContextInterface $context): bool
    {
        return $context instanceof RunContext || $context instanceof GitPreCommitContext;
    }

    public function run(ContextInterface $context): TaskResultInterface
    {
        $config = $this->getConfig()->getOptions();
        if (null === $config['project']) {
            return TaskResult::createSkipped($this, $context);
        }

        // Check the current version
        if (!$this->phpVersionUtility->isSupportedVersion(PHP_VERSION)) {
            return TaskResult::createFailed(
                $this,
                $context,
                sprintf('PHP version %s is unsupported', PHP_VERSION)
            );
        }

        // Check the project version if defined
        if (!$this->phpVersionUtility->isSupportedProjectVersion(PHP_VERSION, $config['project'])) {
            return TaskResult::createFailed(
                $this,
                $context,
                sprintf('This project requires PHP version %s, you have %s', $config['project'], PHP_VERSION)
            );
        }

        return TaskResult::createPassed($this, $context);
    }

    public function withConfig(TaskConfigInterface $config): TaskInterface
    {
        $new = clone $this;
        $new->config = $config;

        return $new;
    }

    public function getConfig(): TaskConfigInterface
    {
        return $this->config;
    }

    public static function getConfigurableOptions(): OptionsResolver
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'project' => null,
        ]);
        $resolver->addAllowedTypes('project', ['null', 'string']);

        return $resolver;
    }
}
