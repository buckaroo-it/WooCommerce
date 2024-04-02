<?php

/**
 * This file is part of the ramsey/uuid library
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @copyright Copyright (c) Ben Ramsey <ben@benramsey.com>
 * @license http://opensource.org/licenses/MIT MIT
 */

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\Ramsey\Uuid\Builder;

use WC_Buckaroo\Dependencies\Ramsey\Uuid\Codec\CodecInterface;
use WC_Buckaroo\Dependencies\Ramsey\Uuid\Exception\BuilderNotFoundException;
use WC_Buckaroo\Dependencies\Ramsey\Uuid\Exception\UnableToBuildUuidException;
use WC_Buckaroo\Dependencies\Ramsey\Uuid\UuidInterface;

/**
 * FallbackBuilder builds a UUID by stepping through a list of UUID builders
 * until a UUID can be constructed without exceptions
 *
 * @psalm-immutable
 */
class FallbackBuilder implements UuidBuilderInterface
{
    /**
     * @var BuilderCollection
     */
    private $builders;

    /**
     * @param BuilderCollection $builders An array of UUID builders
     */
    public function __construct(BuilderCollection $builders)
    {
        $this->builders = $builders;
    }

    /**
     * Builds and returns a UuidInterface instance using the first builder that
     * succeeds
     *
     * @param CodecInterface $codec The codec to use for building this instance
     * @param string $bytes The byte string from which to construct a UUID
     *
     * @return UuidInterface an instance of a UUID object
     *
     * @psalm-pure
     */
    public function build(CodecInterface $codec, string $bytes): UuidInterface
    {
        $lastBuilderException = null;

        foreach ($this->builders as $builder) {
            try {
                return $builder->build($codec, $bytes);
            } catch (UnableToBuildUuidException $exception) {
                $lastBuilderException = $exception;

                continue;
            }
        }

        throw new BuilderNotFoundException(
            'Could not find a suitable builder for the provided codec and fields',
            0,
            $lastBuilderException
        );
    }
}
