<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Parser\Php\Visitor;

use WC_Buckaroo\Dependencies\GrumPHP\Parser\Php\Context\ParserContext;
use PhpParser\NodeVisitor;

interface ContextAwareVisitorInterface extends NodeVisitor
{
    public function setContext(ParserContext $context): void;
}
