<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Parser\Php\Visitor;

use PhpParser\NodeVisitor;

interface ConfigurableVisitorInterface extends NodeVisitor
{
    public function configure(array $options): void;
}
