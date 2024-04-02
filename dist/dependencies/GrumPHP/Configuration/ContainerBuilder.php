<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Configuration;

use WC_Buckaroo\Dependencies\GrumPHP\Util\Filesystem;
use WC_Buckaroo\Dependencies\Symfony\Component\Config\FileLocator;
use WC_Buckaroo\Dependencies\Symfony\Component\Console\DependencyInjection\AddConsoleCommandPass;
use WC_Buckaroo\Dependencies\Symfony\Component\DependencyInjection\ContainerBuilder as SymfonyContainerBuilder;
use WC_Buckaroo\Dependencies\Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class ContainerBuilder
{
    public static function buildFromConfiguration(string $path): SymfonyContainerBuilder
    {
        $filesystem = new Filesystem();
        $container = new SymfonyContainerBuilder();

        // Register extensions
        $container->registerExtension(new GrumPHPExtension());

        // Add compiler passes:
        $container->addCompilerPass(new Compiler\ExtensionCompilerPass());
        $container->addCompilerPass(new Compiler\PhpParserCompilerPass());
        $container->addCompilerPass(new Compiler\TaskCompilerPass());
        $container->addCompilerPass(new Compiler\TestSuiteCompilerPass());
        $container->addCompilerPass(Compiler\RegisterListenersPass::create());
        $container->addCompilerPass(new AddConsoleCommandPass());

        // Load basic service file + custom user configuration
        $configDir = dirname(__DIR__, 2).$filesystem->ensureValidSlashes('/resources/config');
        $loader = new YamlFileLoader($container, new FileLocator($configDir));
        $loader->load('config.yml');
        $loader->load('console.yml');
        $loader->load('fixer.yml');
        $loader->load('formatter.yml');
        $loader->load('linters.yml');
        $loader->load('locators.yml');
        $loader->load('parsers.yml');
        $loader->load('runner.yml');
        $loader->load('services.yml');
        $loader->load('subscribers.yml');
        $loader->load('tasks.yml');
        $loader->load('util.yml');

        // Load grumphp.yml file:
        if ($filesystem->exists($path)) {
            $loader->load($path);
        }

        // Add additional paths
        $container->setParameter('config_file', $path);

        // Compile configuration to make sure that tasks are added to the taskrunner
        $container->compile();

        return $container;
    }
}
