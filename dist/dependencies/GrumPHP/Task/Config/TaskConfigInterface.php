<?php

namespace WC_Buckaroo\Dependencies\GrumPHP\Task\Config;

interface TaskConfigInterface
{
    public function getName(): string;

    public function getOptions(): array;

    public function getMetadata(): Metadata;
}
