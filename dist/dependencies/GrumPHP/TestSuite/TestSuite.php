<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\TestSuite;

class TestSuite implements TestSuiteInterface
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var array
     */
    private $taskNames = [];

    /**
     * TestSuite constructor.
     */
    public function __construct(string $name, array $taskNames)
    {
        $this->taskNames = $taskNames;
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTaskNames(): array
    {
        return $this->taskNames;
    }
}
