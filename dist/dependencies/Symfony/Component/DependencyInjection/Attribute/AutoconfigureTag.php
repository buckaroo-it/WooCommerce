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
 * An attribute to tell how a base type should be tagged.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
#[\WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_Attribute(\WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_Attribute::TARGET_CLASS | \WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_WC_Buckaroo_Attribute::IS_REPEATABLE)]
class AutoconfigureTag extends Autoconfigure
{
    public function __construct(?string $name = null, array $attributes = [])
    {
        parent::__construct(
            tags: [
                [$name ?? 0 => $attributes],
            ]
        );
    }
}
