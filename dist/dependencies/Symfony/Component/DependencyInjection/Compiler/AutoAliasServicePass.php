<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace WC_Buckaroo\Dependencies\Symfony\Component\DependencyInjection\Compiler;

use WC_Buckaroo\Dependencies\Symfony\Component\DependencyInjection\Alias;
use WC_Buckaroo\Dependencies\Symfony\Component\DependencyInjection\ContainerBuilder;
use WC_Buckaroo\Dependencies\Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

/**
 * Sets a service to be an alias of another one, given a format pattern.
 */
class AutoAliasServicePass implements CompilerPassInterface
{
    private $privateAliases = [];

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        foreach ($container->findTaggedServiceIds('auto_alias') as $serviceId => $tags) {
            foreach ($tags as $tag) {
                if (!isset($tag['format'])) {
                    throw new InvalidArgumentException(sprintf('Missing tag information "format" on auto_alias service "%s".', $serviceId));
                }

                $aliasId = $container->getParameterBag()->resolveValue($tag['format']);
                if ($container->hasDefinition($aliasId) || $container->hasAlias($aliasId)) {
                    $alias = new Alias($aliasId, $container->getDefinition($serviceId)->isPublic());
                    $container->setAlias($serviceId, $alias);

                    if (!$alias->isPublic()) {
                        $alias->setPublic(true);
                        $this->privateAliases[] = $alias;
                    }
                }
            }
        }
    }

    /**
     * @internal to be removed in Symfony 6.0
     */
    public function getPrivateAliases(): array
    {
        $privateAliases = $this->privateAliases;
        $this->privateAliases = [];

        return $privateAliases;
    }
}
