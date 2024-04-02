<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Console\Command\Git;

use WC_Buckaroo\Dependencies\GrumPHP\Util\Filesystem;
use WC_Buckaroo\Dependencies\GrumPHP\Util\Paths;
use WC_Buckaroo\Dependencies\Symfony\Component\Console\Command\Command;
use WC_Buckaroo\Dependencies\Symfony\Component\Console\Input\InputInterface;
use WC_Buckaroo\Dependencies\Symfony\Component\Console\Output\OutputInterface;

/**
 * This command is responsible for removing all the configured hooks.
 */
class DeInitCommand extends Command
{
    const COMMAND_NAME = 'git:deinit';

    /**
     * @var array
     */
    protected static $hooks = [
        'pre-commit',
        'commit-msg',
    ];

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var Paths
     */
    private $paths;

    public static function getDefaultName(): string
    {
        return self::COMMAND_NAME;
    }

    public function __construct(
        Filesystem $filesystem,
        Paths $paths
    ) {
        parent::__construct();

        $this->filesystem = $filesystem;
        $this->paths = $paths;
    }

    protected function configure(): void
    {
        $this->setDescription('Removes the commit hooks');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $gitHooksPath = $this->paths->getGitHooksDir();

        foreach (InitCommand::$hooks as $hook) {
            $hookPath = $this->filesystem->buildPath($gitHooksPath, $hook);
            if (!$this->filesystem->exists($hookPath)) {
                continue;
            }

            $this->filesystem->remove($hookPath);
        }

        $output->writeln('<fg=yellow>WC_Buckaroo\Dependencies\GrumPHP stopped sniffing your commits! Too bad ...<fg=yellow>');

        return 0;
    }
}
