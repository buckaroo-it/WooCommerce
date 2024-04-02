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

use WC_Buckaroo\Dependencies\Symfony\Component\DependencyInjection\ContainerInterface;
use WC_Buckaroo\Dependencies\Symfony\Component\DependencyInjection\Definition;
use WC_Buckaroo\Dependencies\Symfony\Component\DependencyInjection\TypedReference;
use WC_Buckaroo\Dependencies\Symfony\Contracts\Service\WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_Attribute\Required;

/**
 * Looks for definitions with autowiring enabled and registers their corresponding "@required" properties.
 *
 * @author Sebastien Morel (Plopix) <morel.seb@gmail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class AutowireRequiredPropertiesPass extends AbstractRecursivePass
{
    /**
     * {@inheritdoc}
     */
    protected function processValue($value, bool $isRoot = false)
    {
        if (\PHP_VERSION_ID < 70400) {
            return $value;
        }
        $value = parent::processValue($value, $isRoot);

        if (!$value instanceof Definition || !$value->isAutowired() || $value->isAbstract() || !$value->getClass()) {
            return $value;
        }
        if (!$reflectionClass = $this->container->getReflectionClass($value->getClass(), false)) {
            return $value;
        }

        $properties = $value->getProperties();
        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            if (!($type = $reflectionProperty->getType()) instanceof \ReflectionNamedType) {
                continue;
            }
            if ((\PHP_VERSION_ID < 80000 || !$reflectionProperty->getAttributes(Required::class))
                && ((false === $doc = $reflectionProperty->getDocComment()) || false === stripos($doc, '@required') || !preg_match('#(?:^/\*\*|\n\s*+\*)\s*+@required(?:\s|\*/$)#i', $doc))
            ) {
                continue;
            }
            if (\array_key_exists($name = $reflectionProperty->getName(), $properties)) {
                continue;
            }

            $type = $type->getName();
            $value->setProperty($name, new TypedReference($type, $type, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $name));
        }

        return $value;
    }
}
