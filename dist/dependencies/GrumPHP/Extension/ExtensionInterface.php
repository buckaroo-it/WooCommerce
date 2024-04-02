<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Extension;

use WC_Buckaroo\Dependencies\Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Interface ExtensionInterface is used for WC_Buckaroo\Dependencies\GrumPHP extensions to interface
 * with WC_Buckaroo\Dependencies\GrumPHP through the service container.
 */
interface ExtensionInterface
{
    public function load(ContainerBuilder $container): void;
}
