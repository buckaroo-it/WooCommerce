<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace WC_Buckaroo\Dependencies\Symfony\Component\EventDispatcher\WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_Attribute;

/**
 * Service tag to autoconfigure event listeners.
 *
 * @author Alexander M. Turek <me@derrabus.de>
 */
#[\WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_Attribute(\WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_Attribute::TARGET_CLASS | \WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_Attribute::TARGET_METHOD | \WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_Attribute::IS_REPEATABLE)]
class AsEventListener
{
    public function __construct(
        public ?string $event = null,
        public ?string $method = null,
        public int $priority = 0,
        public ?string $dispatcher = null,
    ) {
    }
}
