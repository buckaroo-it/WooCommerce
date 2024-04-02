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

namespace WC_Buckaroo\Dependencies\Ramsey\Uuid;

use WC_Buckaroo\Dependencies\Ramsey\Uuid\Builder\BuilderCollection;
use WC_Buckaroo\Dependencies\Ramsey\Uuid\Builder\FallbackBuilder;
use WC_Buckaroo\Dependencies\Ramsey\Uuid\Builder\UuidBuilderInterface;
use WC_Buckaroo\Dependencies\Ramsey\Uuid\Codec\CodecInterface;
use WC_Buckaroo\Dependencies\Ramsey\Uuid\Codec\GuidStringCodec;
use WC_Buckaroo\Dependencies\Ramsey\Uuid\Codec\StringCodec;
use WC_Buckaroo\Dependencies\Ramsey\Uuid\Converter\Number\GenericNumberConverter;
use WC_Buckaroo\Dependencies\Ramsey\Uuid\Converter\NumberConverterInterface;
use WC_Buckaroo\Dependencies\Ramsey\Uuid\Converter\Time\GenericTimeConverter;
use WC_Buckaroo\Dependencies\Ramsey\Uuid\Converter\Time\PhpTimeConverter;
use WC_Buckaroo\Dependencies\Ramsey\Uuid\Converter\TimeConverterInterface;
use WC_Buckaroo\Dependencies\Ramsey\Uuid\Generator\DceSecurityGenerator;
use WC_Buckaroo\Dependencies\Ramsey\Uuid\Generator\DceSecurityGeneratorInterface;
use WC_Buckaroo\Dependencies\Ramsey\Uuid\Generator\NameGeneratorFactory;
use WC_Buckaroo\Dependencies\Ramsey\Uuid\Generator\NameGeneratorInterface;
use WC_Buckaroo\Dependencies\Ramsey\Uuid\Generator\PeclUuidNameGenerator;
use WC_Buckaroo\Dependencies\Ramsey\Uuid\Generator\PeclUuidRandomGenerator;
use WC_Buckaroo\Dependencies\Ramsey\Uuid\Generator\PeclUuidTimeGenerator;
use WC_Buckaroo\Dependencies\Ramsey\Uuid\Generator\RandomGeneratorFactory;
use WC_Buckaroo\Dependencies\Ramsey\Uuid\Generator\RandomGeneratorInterface;
use WC_Buckaroo\Dependencies\Ramsey\Uuid\Generator\TimeGeneratorFactory;
use WC_Buckaroo\Dependencies\Ramsey\Uuid\Generator\TimeGeneratorInterface;
use WC_Buckaroo\Dependencies\Ramsey\Uuid\Guid\GuidBuilder;
use WC_Buckaroo\Dependencies\Ramsey\Uuid\Math\BrickMathCalculator;
use WC_Buckaroo\Dependencies\Ramsey\Uuid\Math\CalculatorInterface;
use WC_Buckaroo\Dependencies\Ramsey\Uuid\Nonstandard\UuidBuilder as NonstandardUuidBuilder;
use WC_Buckaroo\Dependencies\Ramsey\Uuid\Provider\Dce\SystemDceSecurityProvider;
use WC_Buckaroo\Dependencies\Ramsey\Uuid\Provider\DceSecurityProviderInterface;
use WC_Buckaroo\Dependencies\Ramsey\Uuid\Provider\Node\FallbackNodeProvider;
use WC_Buckaroo\Dependencies\Ramsey\Uuid\Provider\Node\NodeProviderCollection;
use WC_Buckaroo\Dependencies\Ramsey\Uuid\Provider\Node\RandomNodeProvider;
use WC_Buckaroo\Dependencies\Ramsey\Uuid\Provider\Node\SystemNodeProvider;
use WC_Buckaroo\Dependencies\Ramsey\Uuid\Provider\NodeProviderInterface;
use WC_Buckaroo\Dependencies\Ramsey\Uuid\Provider\Time\SystemTimeProvider;
use WC_Buckaroo\Dependencies\Ramsey\Uuid\Provider\TimeProviderInterface;
use WC_Buckaroo\Dependencies\Ramsey\Uuid\Rfc4122\UuidBuilder as Rfc4122UuidBuilder;
use WC_Buckaroo\Dependencies\Ramsey\Uuid\Validator\GenericValidator;
use WC_Buckaroo\Dependencies\Ramsey\Uuid\Validator\ValidatorInterface;

use const PHP_INT_SIZE;

/**
 * FeatureSet detects and exposes available features in the current environment
 *
 * A feature set is used by UuidFactory to determine the available features and
 * capabilities of the environment.
 */
class FeatureSet
{
    /**
     * @var bool
     */
    private $disableBigNumber = false;

    /**
     * @var bool
     */
    private $disable64Bit = false;

    /**
     * @var bool
     */
    private $ignoreSystemNode = false;

    /**
     * @var bool
     */
    private $enablePecl = false;

    /**
     * @var UuidBuilderInterface
     */
    private $builder;

    /**
     * @var CodecInterface
     */
    private $codec;

    /**
     * @var DceSecurityGeneratorInterface
     */
    private $dceSecurityGenerator;

    /**
     * @var NameGeneratorInterface
     */
    private $nameGenerator;

    /**
     * @var NodeProviderInterface
     */
    private $nodeProvider;

    /**
     * @var NumberConverterInterface
     */
    private $numberConverter;

    /**
     * @var TimeConverterInterface
     */
    private $timeConverter;

    /**
     * @var RandomGeneratorInterface
     */
    private $randomGenerator;

    /**
     * @var TimeGeneratorInterface
     */
    private $timeGenerator;

    /**
     * @var TimeProviderInterface
     */
    private $timeProvider;

    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @var CalculatorInterface
     */
    private $calculator;

    /**
     * @param bool $useGuids True build UUIDs using the GuidStringCodec
     * @param bool $force32Bit True to force the use of 32-bit functionality
     *     (primarily for testing purposes)
     * @param bool $forceNoBigNumber True to disable the use of moontoast/math
     *     (primarily for testing purposes)
     * @param bool $ignoreSystemNode True to disable attempts to check for the
     *     system node ID (primarily for testing purposes)
     * @param bool $enablePecl True to enable the use of the PeclUuidTimeGenerator
     *     to generate version 1 UUIDs
     */
    public function __construct(
        bool $useGuids = false,
        bool $force32Bit = false,
        bool $forceNoBigNumber = false,
        bool $ignoreSystemNode = false,
        bool $enablePecl = false
    ) {
        $this->disableBigNumber = $forceNoBigNumber;
        $this->disable64Bit = $force32Bit;
        $this->ignoreSystemNode = $ignoreSystemNode;
        $this->enablePecl = $enablePecl;

        $this->setCalculator(new BrickMathCalculator());
        $this->builder = $this->buildUuidBuilder($useGuids);
        $this->codec = $this->buildCodec($useGuids);
        $this->nodeProvider = $this->buildNodeProvider();
        $this->nameGenerator = $this->buildNameGenerator();
        $this->randomGenerator = $this->buildRandomGenerator();
        $this->setTimeProvider(new SystemTimeProvider());
        $this->setDceSecurityProvider(new SystemDceSecurityProvider());
        $this->validator = new GenericValidator();
    }

    /**
     * Returns the builder configured for this environment
     */
    public function getBuilder(): UuidBuilderInterface
    {
        return $this->builder;
    }

    /**
     * Returns the calculator configured for this environment
     */
    public function getCalculator(): CalculatorInterface
    {
        return $this->calculator;
    }

    /**
     * Returns the codec configured for this environment
     */
    public function getCodec(): CodecInterface
    {
        return $this->codec;
    }

    /**
     * Returns the DCE Security generator configured for this environment
     */
    public function getDceSecurityGenerator(): DceSecurityGeneratorInterface
    {
        return $this->dceSecurityGenerator;
    }

    /**
     * Returns the name generator configured for this environment
     */
    public function getNameGenerator(): NameGeneratorInterface
    {
        return $this->nameGenerator;
    }

    /**
     * Returns the node provider configured for this environment
     */
    public function getNodeProvider(): NodeProviderInterface
    {
        return $this->nodeProvider;
    }

    /**
     * Returns the number converter configured for this environment
     */
    public function getNumberConverter(): NumberConverterInterface
    {
        return $this->numberConverter;
    }

    /**
     * Returns the random generator configured for this environment
     */
    public function getRandomGenerator(): RandomGeneratorInterface
    {
        return $this->randomGenerator;
    }

    /**
     * Returns the time converter configured for this environment
     */
    public function getTimeConverter(): TimeConverterInterface
    {
        return $this->timeConverter;
    }

    /**
     * Returns the time generator configured for this environment
     */
    public function getTimeGenerator(): TimeGeneratorInterface
    {
        return $this->timeGenerator;
    }

    /**
     * Returns the validator configured for this environment
     */
    public function getValidator(): ValidatorInterface
    {
        return $this->validator;
    }

    /**
     * Sets the calculator to use in this environment
     */
    public function setCalculator(CalculatorInterface $calculator): void
    {
        $this->calculator = $calculator;
        $this->numberConverter = $this->buildNumberConverter($calculator);
        $this->timeConverter = $this->buildTimeConverter($calculator);

        /** @psalm-suppress RedundantPropertyInitializationCheck */
        if (isset($this->timeProvider)) {
            $this->timeGenerator = $this->buildTimeGenerator($this->timeProvider);
        }
    }

    /**
     * Sets the DCE Security provider to use in this environment
     */
    public function setDceSecurityProvider(DceSecurityProviderInterface $dceSecurityProvider): void
    {
        $this->dceSecurityGenerator = $this->buildDceSecurityGenerator($dceSecurityProvider);
    }

    /**
     * Sets the node provider to use in this environment
     */
    public function setNodeProvider(NodeProviderInterface $nodeProvider): void
    {
        $this->nodeProvider = $nodeProvider;
        $this->timeGenerator = $this->buildTimeGenerator($this->timeProvider);
    }

    /**
     * Sets the time provider to use in this environment
     */
    public function setTimeProvider(TimeProviderInterface $timeProvider): void
    {
        $this->timeProvider = $timeProvider;
        $this->timeGenerator = $this->buildTimeGenerator($timeProvider);
    }

    /**
     * Set the validator to use in this environment
     */
    public function setValidator(ValidatorInterface $validator): void
    {
        $this->validator = $validator;
    }

    /**
     * Returns a codec configured for this environment
     *
     * @param bool $useGuids Whether to build UUIDs using the GuidStringCodec
     */
    private function buildCodec(bool $useGuids = false): CodecInterface
    {
        if ($useGuids) {
            return new GuidStringCodec($this->builder);
        }

        return new StringCodec($this->builder);
    }

    /**
     * Returns a DCE Security generator configured for this environment
     */
    private function buildDceSecurityGenerator(
        DceSecurityProviderInterface $dceSecurityProvider
    ): DceSecurityGeneratorInterface {
        return new DceSecurityGenerator(
            $this->numberConverter,
            $this->timeGenerator,
            $dceSecurityProvider
        );
    }

    /**
     * Returns a node provider configured for this environment
     */
    private function buildNodeProvider(): NodeProviderInterface
    {
        if ($this->ignoreSystemNode) {
            return new RandomNodeProvider();
        }

        return new FallbackNodeProvider(new NodeProviderCollection([
            new SystemNodeProvider(),
            new RandomNodeProvider(),
        ]));
    }

    /**
     * Returns a number converter configured for this environment
     */
    private function buildNumberConverter(CalculatorInterface $calculator): NumberConverterInterface
    {
        return new GenericNumberConverter($calculator);
    }

    /**
     * Returns a random generator configured for this environment
     */
    private function buildRandomGenerator(): RandomGeneratorInterface
    {
        if ($this->enablePecl) {
            return new PeclUuidRandomGenerator();
        }

        return (new RandomGeneratorFactory())->getGenerator();
    }

    /**
     * Returns a time generator configured for this environment
     *
     * @param TimeProviderInterface $timeProvider The time provider to use with
     *     the time generator
     */
    private function buildTimeGenerator(TimeProviderInterface $timeProvider): TimeGeneratorInterface
    {
        if ($this->enablePecl) {
            return new PeclUuidTimeGenerator();
        }

        return (new TimeGeneratorFactory(
            $this->nodeProvider,
            $this->timeConverter,
            $timeProvider
        ))->getGenerator();
    }

    /**
     * Returns a name generator configured for this environment
     */
    private function buildNameGenerator(): NameGeneratorInterface
    {
        if ($this->enablePecl) {
            return new PeclUuidNameGenerator();
        }

        return (new NameGeneratorFactory())->getGenerator();
    }

    /**
     * Returns a time converter configured for this environment
     */
    private function buildTimeConverter(CalculatorInterface $calculator): TimeConverterInterface
    {
        $genericConverter = new GenericTimeConverter($calculator);

        if ($this->is64BitSystem()) {
            return new PhpTimeConverter($calculator, $genericConverter);
        }

        return $genericConverter;
    }

    /**
     * Returns a UUID builder configured for this environment
     *
     * @param bool $useGuids Whether to build UUIDs using the GuidStringCodec
     */
    private function buildUuidBuilder(bool $useGuids = false): UuidBuilderInterface
    {
        if ($useGuids) {
            return new GuidBuilder($this->numberConverter, $this->timeConverter);
        }

        /** @psalm-suppress ImpureArgument */
        return new FallbackBuilder(new BuilderCollection([
            new Rfc4122UuidBuilder($this->numberConverter, $this->timeConverter),
            new NonstandardUuidBuilder($this->numberConverter, $this->timeConverter),
        ]));
    }

    /**
     * Returns true if the PHP build is 64-bit
     */
    private function is64BitSystem(): bool
    {
        return PHP_INT_SIZE === 8 && !$this->disable64Bit;
    }
}
