<?php

namespace WC_Buckaroo\Dependencies\GrumPHP\Task;

use WC_Buckaroo\Dependencies\GrumPHP\Fixer\Provider\FixableProcessResultProvider;
use WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskResult;
use WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskResultInterface;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Context\ContextInterface;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Context\GitPreCommitContext;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Context\RunContext;
use WC_Buckaroo\Dependencies\Symfony\Component\OptionsResolver\OptionsResolver;
use WC_Buckaroo\Dependencies\Symfony\Component\Process\Process;

class ComposerNormalize extends AbstractExternalTask
{
    public function canRunInContext(ContextInterface $context): bool
    {
        return $context instanceof GitPreCommitContext || $context instanceof RunContext;
    }

    public static function getConfigurableOptions(): OptionsResolver
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'indent_size' => null,
            'indent_style' => null,
            'no_check_lock' => false,
            'no_update_lock' => true,
            'use_standalone' => false,
            'verbose' => false,
        ]);

        $resolver->addAllowedTypes('indent_size', ['int', 'null']);
        $resolver->addAllowedTypes('indent_style', ['string', 'null']);
        $resolver->addAllowedValues('indent_style', ['tab', 'space', null]);
        $resolver->addAllowedTypes('no_check_lock', ['bool']);
        $resolver->addAllowedTypes('no_update_lock', ['bool']);
        $resolver->addAllowedTypes('verbose', ['bool']);

        return $resolver;
    }

    public function run(ContextInterface $context): TaskResultInterface
    {
        $config = $this->getConfig()->getOptions();
        $files = $context->getFiles()
            ->path(pathinfo('composer.json', PATHINFO_DIRNAME))
            ->name(pathinfo('composer.json', PATHINFO_BASENAME));

        if (0 === count($files)) {
            return TaskResult::createSkipped($this, $context);
        }

        $executable = $config['use_standalone'] ? 'composer-normalize' : 'composer';
        $arguments = $this->processBuilder->createArgumentsForCommand($executable);
        $arguments->addOptionalArgument('normalize', !$config['use_standalone']);
        $arguments->add('--dry-run');

        if ($config['indent_size'] !== null && $config['indent_style'] !== null) {
            $arguments->addOptionalArgument('--indent-style=%s', $config['indent_style']);
            $arguments->addOptionalArgument('--indent-size=%s', $config['indent_size']);
        }

        $arguments->addOptionalArgument('--no-check-lock', $config['no_check_lock']);
        $arguments->addOptionalArgument('--no-update-lock', $config['no_update_lock']);
        $arguments->addOptionalArgument('-q', $config['verbose']);

        $process = $this->processBuilder->buildProcess($arguments);
        $process->run();

        if (!$process->isSuccessful()) {
            return FixableProcessResultProvider::provide(
                TaskResult::createFailed($this, $context, $this->formatter->format($process)),
                function () use ($arguments): Process {
                    $arguments->removeElement('--dry-run');
                    return $this->processBuilder->buildProcess($arguments);
                }
            );
        }

        return TaskResult::createPassed($this, $context);
    }
}
