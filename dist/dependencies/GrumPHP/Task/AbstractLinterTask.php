<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Task;

use WC_Buckaroo\Dependencies\GrumPHP\Collection\FilesCollection;
use WC_Buckaroo\Dependencies\GrumPHP\Collection\LintErrorsCollection;
use WC_Buckaroo\Dependencies\GrumPHP\Exception\RuntimeException;
use WC_Buckaroo\Dependencies\GrumPHP\Linter\LinterInterface;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Config\EmptyTaskConfig;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Config\TaskConfigInterface;
use WC_Buckaroo\Dependencies\Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @template-covariant Linter extends LinterInterface
 */
abstract class AbstractLinterTask implements TaskInterface
{
    /**
     * @var TaskConfigInterface
     */
    protected $config;

    /**
     * @var Linter
     */
    protected $linter;

    /**
     * @param Linter $linter
     */
    public function __construct(LinterInterface $linter)
    {
        $this->linter = $linter;
        $this->config = new EmptyTaskConfig();
    }

    public static function getConfigurableOptions(): OptionsResolver
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'ignore_patterns' => [],
        ]);

        $resolver->addAllowedTypes('ignore_patterns', ['array']);

        return $resolver;
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

    /**
     * Validates if the linter is installed.
     *
     * @throws RuntimeException
     */
    protected function guardLinterIsInstalled(): void
    {
        if (!$this->linter->isInstalled()) {
            throw new RuntimeException(
                sprintf(
                    'The %s can\'t run on your system. Please install all dependencies.',
                    $this->getConfig()->getName()
                )
            );
        }
    }

    protected function lint(FilesCollection $files): LintErrorsCollection
    {
        $this->guardLinterIsInstalled();

        // Skip ignored patterns:
        $configuration = $this->getConfig()->getOptions();
        foreach ($configuration['ignore_patterns'] as $pattern) {
            $files = $files->notPath($pattern);
        }

        // Lint every file:
        $lintErrors = new LintErrorsCollection();
        foreach ($files as $file) {
            foreach ($this->linter->lint($file) as $error) {
                $lintErrors->add($error);
            }
        }

        return $lintErrors;
    }
}
