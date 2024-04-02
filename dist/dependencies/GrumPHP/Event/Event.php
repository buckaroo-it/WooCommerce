<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Event;

use WC_Buckaroo\Dependencies\Symfony\Contracts\EventDispatcher\Event as SymfonyEventContract;
use WC_Buckaroo\Dependencies\Symfony\Component\EventDispatcher\Event as SymfonyLegacyEvent;

// @codingStandardsIgnoreStart
if (class_exists(SymfonyEventContract::class)) {
    class Event extends SymfonyEventContract
    {
    }
} else {
    class Event extends SymfonyLegacyEvent
    {
    }
}
// @codingStandardsIgnoreEnd
