<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace WC_Buckaroo\Dependencies\Symfony\Component\Config\Definition;

use WC_Buckaroo\Dependencies\Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * Configuration interface.
 *
 * @author Victor Berchet <victor@suumit.com>
 */
interface ConfigurationInterface
{
    /**
     * Generates the configuration tree builder.
     *
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder();
}
