<?php

namespace WC_Buckaroo\Dependencies\Amp\Process;

use WC_Buckaroo\Dependencies\Amp\ByteStream\ClosedException;
use WC_Buckaroo\Dependencies\Amp\ByteStream\OutputStream;
use WC_Buckaroo\Dependencies\Amp\ByteStream\ResourceOutputStream;
use WC_Buckaroo\Dependencies\Amp\ByteStream\StreamException;
use WC_Buckaroo\Dependencies\Amp\Deferred;
use WC_Buckaroo\Dependencies\Amp\Failure;
use WC_Buckaroo\Dependencies\Amp\Promise;

final class ProcessOutputStream implements OutputStream
{
    /** @var \SplQueue */
    private $queuedWrites;

    /** @var bool */
    private $shouldClose = false;

    /** @var ResourceOutputStream */
    private $resourceStream;

    /** @var StreamException|null */
    private $error;

    public function __construct(Promise $resourceStreamPromise)
    {
        $this->queuedWrites = new \SplQueue;
        $resourceStreamPromise->onResolve(function ($error, $resourceStream) {
            if ($error) {
                $this->error = new StreamException("Failed to launch process", 0, $error);

                while (!$this->queuedWrites->isEmpty()) {
                    list(, $deferred) = $this->queuedWrites->shift();
                    $deferred->fail($this->error);
                }

                return;
            }

            while (!$this->queuedWrites->isEmpty()) {
                /**
                 * @var string $data
                 * @var \WC_Buckaroo\Dependencies\Amp\Deferred $deferred
                 */
                list($data, $deferred) = $this->queuedWrites->shift();
                $deferred->resolve($resourceStream->write($data));
            }

            $this->resourceStream = $resourceStream;

            if ($this->shouldClose) {
                $this->resourceStream->close();
            }
        });
    }

    /** @inheritdoc */
    public function write(string $data): Promise
    {
        if ($this->resourceStream) {
            return $this->resourceStream->write($data);
        }

        if ($this->error) {
            return new Failure($this->error);
        }

        if ($this->shouldClose) {
            throw new ClosedException("Stream has already been closed.");
        }

        $deferred = new Deferred;
        $this->queuedWrites->push([$data, $deferred]);

        return $deferred->promise();
    }

    /** @inheritdoc */
    public function end(string $finalData = ""): Promise
    {
        if ($this->resourceStream) {
            return $this->resourceStream->end($finalData);
        }

        if ($this->error) {
            return new Failure($this->error);
        }

        if ($this->shouldClose) {
            throw new ClosedException("Stream has already been closed.");
        }

        $deferred = new Deferred;
        $this->queuedWrites->push([$finalData, $deferred]);

        $this->shouldClose = true;

        return $deferred->promise();
    }

    public function close()
    {
        $this->shouldClose = true;

        if ($this->resourceStream) {
            $this->resourceStream->close();
        } elseif (!$this->queuedWrites->isEmpty()) {
            $error = new ClosedException("Stream closed.");
            do {
                list(, $deferred) = $this->queuedWrites->shift();
                $deferred->fail($error);
            } while (!$this->queuedWrites->isEmpty());
        }
    }
}
