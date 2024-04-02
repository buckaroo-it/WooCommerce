<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Linter\Json;

use WC_Buckaroo\Dependencies\GrumPHP\Linter\LintError;
use WC_Buckaroo\Dependencies\Seld\JsonLint\ParsingException;
use SplFileInfo;

class JsonLintError extends LintError
{
    public static function fromParsingException(SplFileInfo $file, ParsingException $exception): self
    {
        return new self(LintError::TYPE_ERROR, $exception->getMessage(), $file->getPathname(), 0);
    }

    public function __toString(): string
    {
        return sprintf(
            '[%s] %s: %s',
            strtoupper($this->getType()),
            $this->getFile(),
            $this->getError()
        );
    }
}
