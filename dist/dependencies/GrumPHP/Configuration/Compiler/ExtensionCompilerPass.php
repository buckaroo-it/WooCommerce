<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Configuration\Compiler;

use WC_Buckaroo\Dependencies\GrumPHP\Exception\RuntimeException;
use WC_Buckaroo\Dependencies\GrumPHP\Extension\ExtensionInterface;
use WC_Buckaroo\Dependencies\Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WC_Buckaroo\Dependencies\Symfony\Component\DependencyInjection\ContainerBuilder;

class ExtensionCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $extensions = $container->getParameter('extensions');
        $extensions = \is_array($extensions) ? $extensions : [];
        foreach ($extensions as $extensionClass) {
            if (!class_exists($extensionClass)) {
                throw new RuntimeException(sprintf('Invalid extension class specified: %s', $extensionClass));
            }

            $extension = new $extensionClass();
            if (!$extension instanceof ExtensionInterface) {
                throw new RuntimeException(sprintf(
                    'Extension class must implement ExtensionInterface. But `%s` is not.',
                    $extensionClass
                ));
            }

            $extension->load($container);
        }
    }
}
