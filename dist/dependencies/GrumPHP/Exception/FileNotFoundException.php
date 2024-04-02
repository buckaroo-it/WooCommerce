<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Exception;

class FileNotFoundException extends RuntimeException
{
    public function __construct(string $path)
    {
        parent::__construct(sprintf('File "%s" doesn\'t exists.', $path));
    }
}
