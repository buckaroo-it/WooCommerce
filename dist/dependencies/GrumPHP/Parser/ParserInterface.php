<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Parser;

use WC_Buckaroo\Dependencies\GrumPHP\Collection\ParseErrorsCollection;
use SplFileInfo;

interface ParserInterface
{
    public function parse(SplFileInfo $file): ParseErrorsCollection;

    public function isInstalled(): bool;
}
