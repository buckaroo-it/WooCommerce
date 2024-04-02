<?php

namespace WC_Buckaroo\Dependencies\Amp\Parallel\Worker\Internal;

use WC_Buckaroo\Dependencies\Amp\ByteStream;
use WC_Buckaroo\Dependencies\Amp\Parallel\Context\Context;
use WC_Buckaroo\Dependencies\Amp\Parallel\Context\Process;
use WC_Buckaroo\Dependencies\Amp\Promise;
use function WC_Buckaroo\Dependencies\Amp\call;

class WorkerProcess implements Context
{
    /** @var Process */
    private $process;

    public function __construct($script, array $env = [], string $binary = null)
    {
        $this->process = new Process($script, null, $env, $binary);
    }

    public function receive(): Promise
    {
        return $this->process->receive();
    }

    public function send($data): Promise
    {
        return $this->process->send($data);
    }

    public function isRunning(): bool
    {
        return $this->process->isRunning();
    }

    public function start(): Promise
    {
        return call(function () {
            $result = yield $this->process->start();

            $stdout = $this->process->getStdout();
            $stdout->unreference();

            $stderr = $this->process->getStderr();
            $stderr->unreference();

            ByteStream\pipe($stdout, ByteStream\getStdout());
            ByteStream\pipe($stderr, ByteStream\getStderr());

            return $result;
        });
    }

    public function kill(): void
    {
        if ($this->process->isRunning()) {
            $this->process->kill();
        }
    }

    public function join(): Promise
    {
        return $this->process->join();
    }
}
