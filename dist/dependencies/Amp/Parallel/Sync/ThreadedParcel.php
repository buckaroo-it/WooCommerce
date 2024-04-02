<?php

namespace WC_Buckaroo\Dependencies\Amp\Parallel\Sync;

use WC_Buckaroo\Dependencies\Amp\Promise;
use WC_Buckaroo\Dependencies\Amp\Success;
use WC_Buckaroo\Dependencies\Amp\Sync\ThreadedMutex;
use function WC_Buckaroo\Dependencies\Amp\call;

/**
 * A thread-safe container that shares a value between multiple threads.
 *
 * @deprecated ext-pthreads development has been halted, see https://github.com/krakjoe/pthreads/issues/929
 */
final class ThreadedParcel implements Parcel
{
    /** @var ThreadedMutex */
    private $mutex;

    /** @var \Threaded */
    private $storage;

    /**
     * Creates a new shared object container.
     *
     * @param mixed $value The value to store in the container.
     */
    public function __construct($value)
    {
        $this->mutex = new ThreadedMutex;
        $this->storage = new Internal\ParcelStorage($value);
    }

    /**
     * {@inheritdoc}
     */
    public function unwrap(): Promise
    {
        return new Success($this->storage->get());
    }

    /**
     * @return \WC_Buckaroo\Dependencies\Amp\Promise
     */
    public function synchronized(callable $callback): Promise
    {
        return call(function () use ($callback): \Generator {
            /** @var \WC_Buckaroo\Dependencies\Amp\Sync\Lock $lock */
            $lock = yield $this->mutex->acquire();

            try {
                $result = yield call($callback, $this->storage->get());

                if ($result !== null) {
                    $this->storage->set($result);
                }
            } finally {
                $lock->release();
            }

            return $result;
        });
    }
}
