<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Collection;

use WC_Buckaroo\Dependencies\Doctrine\Common\Collections\ArrayCollection;

/**
 * @extends ArrayCollection<int, \WC_Buckaroo\Dependencies\GrumPHP\Parser\ParseError>
 */
class ParseErrorsCollection extends ArrayCollection
{
    public function __toString(): string
    {
        $errors = [];
        foreach ($this->getIterator() as $error) {
            $errors[] = $error->__toString();
        }

        return implode(PHP_EOL, $errors);
    }
}
