<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Task;

use WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskResult;
use WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskResultInterface;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Context\ContextInterface;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Context\GitPreCommitContext;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Context\RunContext;
use WC_Buckaroo\Dependencies\Symfony\Component\OptionsResolver\OptionsResolver;

class Pest extends AbstractExternalTask
{
    public static function getConfigurableOptions(): OptionsResolver
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'config_file' => null,
            'testsuite' => null,
            'group' => [],
            'always_execute' => false,
        ]);

        $resolver->addAllowedTypes('config_file', ['null', 'string']);
        $resolver->addAllowedTypes('testsuite', ['null', 'string']);
        $resolver->addAllowedTypes('group', ['array']);
        $resolver->addAllowedTypes('always_execute', ['bool']);

        return $resolver;
    }

    public function canRunInContext(ContextInterface $context): bool
    {
        return $context instanceof GitPreCommitContext || $context instanceof RunContext;
    }

    public function run(ContextInterface $context): TaskResultInterface
    {
        $config = $this->getConfig()->getOptions();

        $files = $context->getFiles()->name('*.php');
        if (0 === \count($files) && !$config['always_execute']) {
            return TaskResult::createSkipped($this, $context);
        }

        $arguments = $this->processBuilder->createArgumentsForCommand('pest');
        $arguments->addOptionalArgument('--configuration=%s', $config['config_file']);
        $arguments->addOptionalArgument('--testsuite=%s', $config['testsuite']);
        $arguments->addOptionalCommaSeparatedArgument('--group=%s', $config['group']);

        $process = $this->processBuilder->buildProcess($arguments);
        $process->run();

        if (!$process->isSuccessful()) {
            return TaskResult::createFailed($this, $context, $this->formatter->format($process));
        }

        return TaskResult::createPassed($this, $context);
    }
}
