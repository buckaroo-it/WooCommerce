<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Task\Git;

use WC_Buckaroo\Dependencies\Gitonomy\Git\Exception\ProcessException;
use WC_Buckaroo\Dependencies\GrumPHP\Git\GitRepository;
use WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskResult;
use WC_Buckaroo\Dependencies\GrumPHP\Runner\TaskResultInterface;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Config\EmptyTaskConfig;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Config\TaskConfigInterface;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Context\ContextInterface;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Context\GitPreCommitContext;
use WC_Buckaroo\Dependencies\GrumPHP\Task\Context\RunContext;
use WC_Buckaroo\Dependencies\GrumPHP\Util\Regex;
use WC_Buckaroo\Dependencies\GrumPHP\Task\TaskInterface;
use WC_Buckaroo\Dependencies\Symfony\Component\OptionsResolver\OptionsResolver;

class BranchName implements TaskInterface
{
    /**
     * @var TaskConfigInterface
     */
    private $config;

    /**
     * @var GitRepository
     */
    private $repository;

    public function __construct(GitRepository $repository)
    {
        $this->config = new EmptyTaskConfig();
        $this->repository = $repository;
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
            'blacklist' => [],
            'whitelist' => [],
            'additional_modifiers' => '',
            'allow_detached_head' => true,
        ]);

        $resolver->addAllowedTypes('blacklist', ['array']);
        $resolver->addAllowedTypes('whitelist', ['array']);
        $resolver->addAllowedTypes('additional_modifiers', ['string']);
        $resolver->addAllowedTypes('allow_detached_head', ['bool']);

        return $resolver;
    }

    public function canRunInContext(ContextInterface $context): bool
    {
        return $context instanceof RunContext || $context instanceof GitPreCommitContext;
    }

    public function run(ContextInterface $context): TaskResultInterface
    {
        $config = $this->getConfig()->getOptions();
        $isBlacklisted = false;
        $errors = [];

        try {
            $name = trim((string) $this->repository->run('symbolic-ref', ['HEAD', '--short']));
        } catch (ProcessException $e) {
            if ($config['allow_detached_head']) {
                return TaskResult::createPassed($this, $context);
            }
            $message = 'Branch naming convention task is not allowed on a detached HEAD.';

            return TaskResult::createFailed($this, $context, $message);
        }

        foreach ($config['blacklist'] as $rule) {
            $regex = new Regex($rule);

            $additionalModifiersArray = array_filter(str_split($config['additional_modifiers']));
            array_map([$regex, 'addPatternModifier'], $additionalModifiersArray);

            if (preg_match((string)$regex, $name)) {
                $errors[] = sprintf('Matched blacklist rule: %s', $rule);
                $isBlacklisted = true;
            }
        }
        foreach ($config['whitelist'] as $rule) {
            $regex = new Regex($rule);

            $additionalModifiersArray = array_filter(str_split($config['additional_modifiers']));
            array_map([$regex, 'addPatternModifier'], $additionalModifiersArray);
            
            if (preg_match((string) $regex, $name)) {
                if ($isBlacklisted) {
                    $errors[] = sprintf('Matched whitelist rule: %s (IGNORED due to presence in blacklist)', $rule);
                    
                    continue;
                }
                
                return TaskResult::createPassed($this, $context);
            }

            $errors[] = sprintf('Whitelist rule not matched: %s', $rule);
        }

        if (\count($errors)) {
            return TaskResult::createFailed($this, $context, implode(PHP_EOL, $errors));
        }

        return TaskResult::createPassed($this, $context);
    }
}
