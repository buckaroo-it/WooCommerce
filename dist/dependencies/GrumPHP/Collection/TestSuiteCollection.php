<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Collection;

use WC_Buckaroo\Dependencies\Doctrine\Common\Collections\ArrayCollection;
use WC_Buckaroo\Dependencies\GrumPHP\Exception\InvalidArgumentException;
use WC_Buckaroo\Dependencies\GrumPHP\TestSuite\TestSuiteInterface;

/**
 * @extends ArrayCollection<string, TestSuiteInterface>
 */
class TestSuiteCollection extends ArrayCollection
{
    public function getRequired(string $name): TestSuiteInterface
    {
        if (!$result = $this->get($name)) {
            throw InvalidArgumentException::unknownTestSuite($name);
        }

        return $result;
    }

    public function getOptional(string $name): ?TestSuiteInterface
    {
        return $this->get($name);
    }
}
