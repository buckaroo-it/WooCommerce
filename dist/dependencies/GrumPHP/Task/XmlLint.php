<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Task;

use WC_Buckaroo\Dependencies\GrumPHP\Exception\RuntimeException;
use WC_Buckaroo\Dependencies\GrumPHP\Linter\Xml\XmlLinter;
use WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskResult;
use WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskResultInterface;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Context\ContextInterface;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Context\GitPreCommitContext;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Context\RunContext;
use WC_Buckaroo\Dependencies\Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractLinterTask<XmlLinter>
 */
class XmlLint extends AbstractLinterTask
{
    /**
     * @var XmlLinter
     */
    protected $linter;

    public static function getConfigurableOptions(): OptionsResolver
    {
        $resolver = parent::getConfigurableOptions();
        $resolver->setDefaults([
            'load_from_net' => false,
            'x_include' => false,
            'dtd_validation' => false,
            'scheme_validation' => false,
            'triggered_by' => ['xml'],
        ]);

        $resolver->addAllowedTypes('load_from_net', ['bool']);
        $resolver->addAllowedTypes('x_include', ['bool']);
        $resolver->addAllowedTypes('dtd_validation', ['bool']);
        $resolver->addAllowedTypes('scheme_validation', ['bool']);
        $resolver->addAllowedTypes('triggered_by', ['array']);

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
        $config = $this->getConfig()->getOptions();
        $files = $context->getFiles()->extensions($config['triggered_by']);
        if (0 === \count($files)) {
            return TaskResult::createSkipped($this, $context);
        }

        $this->linter->setLoadFromNet($config['load_from_net']);
        $this->linter->setXInclude($config['x_include']);
        $this->linter->setDtdValidation($config['dtd_validation']);
        $this->linter->setSchemeValidation($config['scheme_validation']);

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
