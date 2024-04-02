<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace WC_Buckaroo\Dependencies\Symfony\Component\DependencyInjection\WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_Attribute;

/**
 * An attribute to tell how a base type should be autoconfigured.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
#[\WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_Attribute(\WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_Attribute::TARGET_CLASS | \WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_Attribute::IS_REPEATABLE)]
class Autoconfigure
{
    public function __construct(
        public ?array $tags = null,
        public ?array $calls = null,
        public ?array $bind = null,
        public bool|string|null $lazy = null,
        public ?bool $public = null,
        public ?bool $shared = null,
        public ?bool $autowire = null,
        public ?array $properties = null,
        public array|string|null $configurator = null,
    ) {
    }
}
