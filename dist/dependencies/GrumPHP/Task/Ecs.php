<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Task;

use WC_Buckaroo\Dependencies\GrumPHP\Collection\FilesCollection;
use WC_Buckaroo\Dependencies\GrumPHP\Collection\ProcessArgumentsCollection;
use WC_Buckaroo\Dependencies\GrumPHP\Fixer\Provider\FixableProcessResultProvider;
use WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskResult;
use WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskResultInterface;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Context\ContextInterface;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Context\GitPreCommitContext;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Context\RunContext;
use WC_Buckaroo\Dependencies\Symfony\Component\OptionsResolver\OptionsResolver;
use WC_Buckaroo\Dependencies\Symfony\Component\Process\Process;

/**
 * Ecs task.
 */
class Ecs extends AbstractExternalTask
{
    public static function getConfigurableOptions(): OptionsResolver
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'paths' => [],
            'clear-cache' => false,
            'no-progress-bar' => true,
            'config' => null,
            'level' => null,
            'triggered_by' => ['php'],
            'files_on_pre_commit' => false,
        ]);

        $resolver->addAllowedTypes('paths', ['array']);
        $resolver->addAllowedTypes('clear-cache', ['bool']);
        $resolver->addAllowedTypes('no-progress-bar', ['bool']);
        $resolver->addAllowedTypes('config', ['null', 'string']);
        $resolver->addAllowedTypes('level', ['null', 'string']);
        $resolver->addAllowedTypes('triggered_by', ['array']);
        $resolver->addAllowedTypes('files_on_pre_commit', ['bool']);

        return $resolver;
    }

    public function canRunInContext(ContextInterface $context): bool
    {
        return $context instanceof GitPreCommitContext || $context instanceof RunContext;
    }

    public function run(ContextInterface $context): TaskResultInterface
    {
        $config = $this->getConfig()->getOptions();

        $files = $context->getFiles()
            ->extensions($config['triggered_by'])
            ->paths($config['paths']);

        if (0 === \count($files)) {
            return TaskResult::createSkipped($this, $context);
        }

        $arguments = $this->processBuilder->createArgumentsForCommand('ecs');
        $arguments->add('check');

        $arguments->addOptionalArgument('--config=%s', $config['config']);
        $arguments->addOptionalArgument('--level=%s', $config['level']);
        $arguments->addOptionalArgument('--clear-cache', $config['clear-cache']);
        $arguments->addOptionalArgument('--no-progress-bar', $config['no-progress-bar']);
        $arguments->addOptionalArgument('--ansi', true);
        $arguments->addOptionalArgument('--no-interaction', true);
        $this->addPaths($arguments, $context, $files, $config);

        $process = $this->processBuilder->buildProcess($arguments);
        $process->run();

        if (!$process->isSuccessful()) {
            return FixableProcessResultProvider::provide(
                TaskResult::createFailed($this, $context, $this->formatter->format($process)),
                function () use ($arguments): Process {
                    $arguments->add('--fix');

                    return $this->processBuilder->buildProcess($arguments);
                }
            );
        }

        return TaskResult::createPassed($this, $context);
    }

    /**
     * This method adds the newly committed files in pre commit context if you enabled the files_on_pre_commit flag.
     * In other cases, it falls back to the configured paths.
     * If no paths have been set, the paths from inside your ECS configuration file will be used.
     */
    private function addPaths(
        ProcessArgumentsCollection $arguments,
        ContextInterface $context,
        FilesCollection $files,
        array $config
    ): void {
        if ($context instanceof GitPreCommitContext && $config['files_on_pre_commit']) {
            $arguments->addFiles($files);
            return;
        }

        $arguments->addArgumentArray('%s', $config['paths']);
    }
}
