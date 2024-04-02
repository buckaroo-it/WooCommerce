<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Console;

use WC_Buckaroo\Dependencies\Symfony\Component\Console\Application;
use WC_Buckaroo\Dependencies\Symfony\Component\Console\Input\InputOption;

class ApplicationConfigurator
{
    const APP_NAME = 'GrumPHP';
    const APP_VERSION = '1.13.0';

    public function configure(Application $application): void
    {
        $application->setVersion(self::APP_VERSION);
        $application->setName(self::APP_NAME);
        $this->registerInputDefinitions($application);
    }

    private function registerInputDefinitions(Application $application): void
    {
        $definition = $application->getDefinition();
        $definition->addOption(
            new InputOption(
                'config',
                'c',
                InputOption::VALUE_REQUIRED,
                'Path to config'
            )
        );
    }
}
