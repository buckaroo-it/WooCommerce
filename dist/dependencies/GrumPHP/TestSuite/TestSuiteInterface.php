<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\TestSuite;

interface TestSuiteInterface
{
    public function getName(): string;

    public function getTaskNames(): array;
}
