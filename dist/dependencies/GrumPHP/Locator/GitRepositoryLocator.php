<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Locator;

use WC_Buckaroo\Dependencies\Gitonomy\Git\Repository;
use WC_Buckaroo\Dependencies\GrumPHP\Util\Paths;

class GitRepositoryLocator
{
    /**
     * @var Paths
     */
    private $paths;

    public function __construct(Paths $paths)
    {
        $this->paths = $paths;
    }

    public function locate(array $options): Repository
    {
        return new Repository(
            $this->paths->getGitRepositoryDir(),
            array_merge(
                [
                    'working_dir' => $this->paths->getGitWorkingDir(),
                ],
                $options
            )
        );
    }
}
