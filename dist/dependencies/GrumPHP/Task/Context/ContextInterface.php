<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Task\Context;

use WC_Buckaroo\Dependencies\GrumPHP\Collection\FilesCollection;

interface ContextInterface
{
    public function getFiles(): FilesCollection;
}
