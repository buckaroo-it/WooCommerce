<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Task;

use WC_Buckaroo\Dependencies\GrumPHP\Exception\RuntimeException;
use WC_Buckaroo\Dependencies\GrumPHP\Linter\Json\JsonLinter;
use WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskResult;
use WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskResultInterface;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Context\ContextInterface;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Context\GitPreCommitContext;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Context\RunContext;
use WC_Buckaroo\Dependencies\Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractLinterTask<JsonLinter>
 */
class JsonLint extends AbstractLinterTask
{
    /**
     * @var JsonLinter
     */
    protected $linter;

    public static function getConfigurableOptions(): OptionsResolver
    {
        $resolver = parent::getConfigurableOptions();
        $resolver->setDefaults([
            'detect_key_conflicts' => false,
        ]);

        $resolver->addAllowedTypes('detect_key_conflicts', ['bool']);

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
        $files = $context->getFiles()->name('*.json');
        if (0 === \count($files)) {
            return TaskResult::createSkipped($this, $context);
        }

        $config = $this->getConfig()->getOptions();
        $this->linter->setDetectKeyConflicts($config['detect_key_conflicts']);

        try {
            $lintErrors = $this->lint($files);
        } catch (RuntimeException $e) {
            return TaskResult::createFailed($this, $context, $e->getMessage());
        }

        if ($lintErrors->count()) {
            return TaskResult::createFailed($this, $context, (string) $lintErrors);
        }

        return TaskResult::createPassed($this, $context);
    }
}
