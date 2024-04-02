<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Task\Context;

use WC_Buckaroo\Dependencies\GrumPHP\Collection\FilesCollection;

class RunContext implements ContextInterface
{
    private $files;

    public function __construct(FilesCollection $files)
    {
        $this->files = $files;
    }

    public function getFiles(): FilesCollection
    {
        return $this->files;
    }
}
