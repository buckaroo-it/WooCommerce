<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Event\Dispatcher\Bridge;

use WC_Buckaroo\Dependencies\GrumPHP\Event\Dispatcher\EventDispatcherInterface;
use WC_Buckaroo\Dependencies\GrumPHP\Event\Event;
use WC_Buckaroo\Dependencies\Symfony\Component\EventDispatcher\EventDispatcherInterface as SymfonyLegacyEventDispatcher;
use WC_Buckaroo\Dependencies\Symfony\Contracts\EventDispatcher\EventDispatcherInterface as SymfonyEventDispatcherContract;

class SymfonyEventDispatcher implements EventDispatcherInterface
{
    /**
     * @var SymfonyLegacyEventDispatcher|SymfonyEventDispatcherContract
     */
    private $dispatcher;

    /**
     * @param SymfonyLegacyEventDispatcher|SymfonyEventDispatcherContract $eventDispatcher
     */
    public function __construct($eventDispatcher)
    {
        $this->dispatcher = $eventDispatcher;
    }

    public function dispatch(Event $event, string $name = null): void
    {
        $interfacesImplemented = class_implements($this->dispatcher);
        if (in_array(SymfonyEventDispatcherContract::class, $interfacesImplemented, true)) {
            /**
             * @psalm-suppress InvalidArgument
             * @psalm-suppress TooManyArguments
             */
            $this->dispatcher->dispatch($event, $name);
            return;
        }

        /**
         * @psalm-suppress InvalidArgument
         * @psalm-suppress TooManyArguments
         */
        $this->dispatcher->dispatch($name, $event);
    }
}
