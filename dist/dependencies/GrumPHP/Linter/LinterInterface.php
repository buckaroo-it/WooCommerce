<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Linter;

use WC_Buckaroo\Dependencies\GrumPHP\Collection\LintErrorsCollection;
use SplFileInfo;

interface LinterInterface
{
    public function lint(SplFileInfo $file): LintErrorsCollection;

    public function isInstalled(): bool;
}
