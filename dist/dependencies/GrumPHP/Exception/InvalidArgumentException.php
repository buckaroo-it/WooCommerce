<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Exception;

class InvalidArgumentException extends RuntimeException
{
    public static function unknownTestSuite(string $testSuiteName): self
    {
        return new self(sprintf('Unknown testsuite specified: %s', $testSuiteName));
    }
}
