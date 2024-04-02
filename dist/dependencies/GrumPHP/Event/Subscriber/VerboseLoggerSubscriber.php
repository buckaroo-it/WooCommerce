<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Event\Subscriber;

use WC_Buckaroo\Dependencies\GrumPHP\Configuration\GuessedPaths;
use WC_Buckaroo\Dependencies\Monolog\Handler\StreamHandler;
use WC_Buckaroo\Dependencies\Monolog\Logger;
use WC_Buckaroo\Dependencies\Symfony\Component\Console\ConsoleEvents;
use WC_Buckaroo\Dependencies\Symfony\Component\Console\Event\ConsoleCommandEvent;
use WC_Buckaroo\Dependencies\Symfony\Component\EventDispatcher\EventSubscriberInterface;
use WC_Buckaroo\Dependencies\Symfony\Component\Console\Output\OutputInterface;

class VerboseLoggerSubscriber implements EventSubscriberInterface
{
    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var GuessedPaths
     */
    private $guessedPaths;

    public function __construct(Logger $logger, GuessedPaths $guessedPaths)
    {
        $this->logger = $logger;
        $this->guessedPaths = $guessedPaths;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => ['onCommand', PHP_INT_MAX],
        ];
    }

    public function onCommand(ConsoleCommandEvent $event): void
    {
        $output = $event->getOutput();
        if (!$output->isVerbose()) {
            return;
        }

        $this->logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
        $this->logGuessedPaths($output);
    }

    private function logGuessedPaths(OutputInterface $output): void
    {
        $output->writeln('Config file: '. $this->guessedPaths->getConfigFile());
        $output->writeln('Working dir: '. $this->guessedPaths->getWorkingDir());
        $output->writeln('Project dir: '. $this->guessedPaths->getProjectDir());
        $output->writeln('GIT working dir: '. $this->guessedPaths->getGitWorkingDir());
        $output->writeln('GIT repository dir: '. $this->guessedPaths->getGitRepositoryDir());
        $output->writeln('Bin dir: '. $this->guessedPaths->getBinDir());
        $output->writeln('Composer file: '. $this->guessedPaths->getComposerFile()->getPath());
    }
}
