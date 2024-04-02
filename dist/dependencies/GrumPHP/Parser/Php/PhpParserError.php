<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Parser\Php;

use WC_Buckaroo\Dependencies\GrumPHP\Parser\ParseError;
use PhpParser\Error;

class PhpParserError extends ParseError
{
    public static function fromParseException(Error $exception, string $filename): self
    {
        return new self(
            ParseError::TYPE_FATAL,
            $exception->getRawMessage(),
            $filename,
            $exception->getStartLine()
        );
    }
}
