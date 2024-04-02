<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace WC_Buckaroo\Dependencies\Symfony\Component\OptionsResolver\Exception;

/**
 * Thrown when two lazy options have a cyclic dependency.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class OptionDefinitionException extends \LogicException implements ExceptionInterface
{
}
