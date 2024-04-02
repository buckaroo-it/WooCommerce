<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Parser\Php\Visitor;

use PhpParser\Node;
use WC_Buckaroo\Dependencies\Symfony\Component\OptionsResolver\OptionsResolver;

class ForbiddenFunctionCallsVisitor extends AbstractVisitor implements ConfigurableVisitorInterface
{
    /**
     * @var array
     */
    private $blacklist = [];

    public function configure(array $options): void
    {
        $resolver = new OptionsResolver();

        $resolver->setDefaults([
            'blacklist' => [],
        ]);

        $resolver->setAllowedTypes('blacklist', ['array']);

        $config = $resolver->resolve($options);
        $this->blacklist = $config['blacklist'];
    }

    /**
     * @psalm-suppress UndefinedPropertyFetch
     * @psalm-suppress PossiblyInvalidCast
     */
    public function leaveNode(Node $node): void
    {

        if (!$node instanceof Node\Expr\FuncCall || !$node->name instanceof Node\Name) {
            return;
        }


        $function = (string) $node->name;
        if (!\in_array($function, $this->blacklist, false)) {
            return;
        }

        $this->addError(
            sprintf('Found blacklisted "%s" function call', $function),
            $node->getLine()
        );
    }
}
