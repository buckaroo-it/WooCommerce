<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Event\Dispatcher;

use WC_Buckaroo\Dependencies\GrumPHP\Event\Event;

interface EventDispatcherInterface
{
    public function dispatch(Event $event, string $name = null): void;
}
