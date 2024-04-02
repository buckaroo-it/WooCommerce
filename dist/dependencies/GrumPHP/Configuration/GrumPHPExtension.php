<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Configuration;

use WC_Buckaroo\Dependencies\GrumPHP\Exception\DeprecatedException;
use WC_Buckaroo\Dependencies\Symfony\Component\Config\Definition\ConfigurationInterface;
use WC_Buckaroo\Dependencies\Symfony\Component\DependencyInjection\ContainerBuilder;
use WC_Buckaroo\Dependencies\Symfony\Component\DependencyInjection\Extension\Extension;

class GrumPHPExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $this->loadInternal(
            $this->processConfiguration(
                $this->getConfiguration($configs, $container),
                $configs
            ),
            $container
        );
    }

    public function getConfiguration(array $config, ContainerBuilder $container): ConfigurationInterface
    {
        return new Configuration();
    }

    public function getAlias(): string
    {
        return 'grumphp';
    }

    private function loadInternal(array $config, ContainerBuilder $container): void
    {
        foreach ($config as $key => $value) {
            // We require using grumphp instead of parameters at this point:
            if ($container->hasParameter($key)) {
                throw DeprecatedException::directParameterConfiguration($key);
            }

            $container->setParameter($key, $value);
        }
    }
}
