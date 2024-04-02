<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Configuration\Compiler;

use WC_Buckaroo\Dependencies\GrumPHP\Collection\TestSuiteCollection;
use WC_Buckaroo\Dependencies\GrumPHP\TestSuite\TestSuite;
use WC_Buckaroo\Dependencies\Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WC_Buckaroo\Dependencies\Symfony\Component\DependencyInjection\ContainerBuilder;
use WC_Buckaroo\Dependencies\Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use WC_Buckaroo\Dependencies\Symfony\Component\OptionsResolver\OptionsResolver;

class TestSuiteCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $testSuites = $container->getParameter('testsuites');
        $testSuites = is_array($testSuites) ? $testSuites : [];
        $registeredTasks = $container->getParameter('grumphp.tasks.configured');
        $registeredTasks = is_array($registeredTasks) ? $registeredTasks : [];
        $optionsResolver = $this->createOptionsResolver($registeredTasks);

        $collection = new TestSuiteCollection();
        foreach ($testSuites as $name => $config) {
            $config = $optionsResolver->resolve($config);
            $collection->set($name, new TestSuite($name, $config['tasks']));
        }

        $container->set(TestSuiteCollection::class, $collection);
    }

    private function createOptionsResolver(array $registeredTasks): OptionsResolver
    {
        $options = new OptionsResolver();
        $options->setRequired(['tasks']);
        $options->setAllowedTypes('tasks', ['array']);
        $options->setAllowedValues('tasks', function (array $value) use ($registeredTasks) {
            foreach ($value as $task) {
                if (!\in_array($task, $registeredTasks, true)) {
                    throw new InvalidOptionsException(sprintf(
                        'The testsuite option "tasks" contains the unknow task "%s". Expected one of %s',
                        $task,
                        implode(',', $registeredTasks)
                    ));
                }
            }

            return true;
        });

        return $options;
    }
}
