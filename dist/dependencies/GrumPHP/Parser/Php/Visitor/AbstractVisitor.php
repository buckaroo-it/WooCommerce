<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Parser\Php\Visitor;

use WC_Buckaroo\Dependencies\GrumPHP\Parser\ParseError;
use WC_Buckaroo\Dependencies\GrumPHP\Parser\Php\Context\ParserContext;
use WC_Buckaroo\Dependencies\GrumPHP\Parser\Php\PhpParserError;
use PhpParser\NodeVisitorAbstract;

/**
 * @psalm-suppress MissingConstructor
 */
class AbstractVisitor extends NodeVisitorAbstract implements ContextAwareVisitorInterface
{
    /**
     * @var ParserContext
     */
    protected $context;

    public function setContext(ParserContext $context): void
    {
        $this->context = $context;
    }

    protected function addError(string $message, int $line = -1, string $type = ParseError::TYPE_ERROR): void
    {
        $errors = $this->context->getErrors();
        $fileName = $this->context->getFile()->getPath();
        $errors->add(new PhpParserError($type, $message, $fileName, $line));
    }
}
