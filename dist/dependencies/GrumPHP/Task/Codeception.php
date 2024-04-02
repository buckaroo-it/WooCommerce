<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Task;

use WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskResult;
use WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskResultInterface;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Context\ContextInterface;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Context\GitPreCommitContext;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Context\RunContext;
use WC_Buckaroo\Dependencies\Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Codeception task.
 */
class Codeception extends AbstractExternalTask
{
    public static function getConfigurableOptions(): OptionsResolver
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'config_file' => null,
            'suite' => null,
            'test' => null,
            'fail_fast' => false,
        ]);

        $resolver->addAllowedTypes('config_file', ['null', 'string']);
        $resolver->addAllowedTypes('suite', ['null', 'string']);
        $resolver->addAllowedTypes('test', ['null', 'string']);
        $resolver->addAllowedTypes('fail_fast', ['bool']);

        return $resolver;
    }

    /**
     * {@inheritdoc}
     */
    public function canRunInContext(ContextInterface $context): bool
    {
        return $context instanceof GitPreCommitContext || $context instanceof RunContext;
    }

    /**
     * {@inheritdoc}
     */
    public function run(ContextInterface $context): TaskResultInterface
    {
        $files = $context->getFiles()->name('*.php');
        if (0 === \count($files)) {
            return TaskResult::createSkipped($this, $context);
        }

        $config = $this->getConfig()->getOptions();

        $arguments = $this->processBuilder->createArgumentsForCommand('codecept');
        $arguments->add('run');
        $arguments->addOptionalArgument('--config=%s', $config['config_file']);
        $arguments->addOptionalArgument('--fail-fast', $config['fail_fast']);
        $arguments->addOptionalArgument('%s', $config['suite']);
        $arguments->addOptionalArgument('%s', $config['test']);

        $process = $this->processBuilder->buildProcess($arguments);
        $process->run();

        if (!$process->isSuccessful()) {
            return TaskResult::createFailed($this, $context, $this->formatter->format($process));
        }

        return TaskResult::createPassed($this, $context);
    }
}
