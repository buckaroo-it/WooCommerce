<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Runner\Parallel;

use WC_Buckaroo\Dependencies\Amp\Parallel\Worker\DefaultPool;
use WC_Buckaroo\Dependencies\Amp\Parallel\Worker\Pool;
use WC_Buckaroo\Dependencies\GrumPHP\Configuration\Model\ParallelConfig;

class PoolFactory
{
    /**
     * @var ParallelConfig
     */
    private $config;

    public function __construct(ParallelConfig $config)
    {
        $this->config = $config;
    }

    public function create(): Pool
    {
        return new DefaultPool(
            $this->config->getMaxWorkers()
        );
    }
}
