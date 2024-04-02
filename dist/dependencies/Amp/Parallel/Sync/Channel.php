<?php

namespace WC_Buckaroo\Dependencies\Amp\Parallel\Sync;

use WC_Buckaroo\Dependencies\Amp\Promise;

/**
 * Interface for sending messages between execution contexts.
 */
interface Channel
{
    /**
     * @return \WC_Buckaroo\Dependencies\Amp\Promise<mixed>
     *
     * @throws \WC_Buckaroo\Dependencies\Amp\Parallel\Context\StatusError Thrown if the context has not been started.
     * @throws \WC_Buckaroo\Dependencies\Amp\Parallel\Sync\SynchronizationError If the context has not been started or the context
     *     unexpectedly ends.
     * @throws \WC_Buckaroo\Dependencies\Amp\Parallel\Sync\ChannelException If receiving from the channel fails.
     * @throws \WC_Buckaroo\Dependencies\Amp\Parallel\Sync\SerializationException If unserializing the data fails.
     */
    public function receive(): Promise;

    /**
     * @param mixed $data
     *
     * @return \WC_Buckaroo\Dependencies\Amp\Promise<int> Resolves with the number of bytes sent on the channel.
     *
     * @throws \WC_Buckaroo\Dependencies\Amp\Parallel\Context\StatusError Thrown if the context has not been started.
     * @throws \WC_Buckaroo\Dependencies\Amp\Parallel\Sync\SynchronizationError If the context has not been started or the context
     *     unexpectedly ends.
     * @throws \WC_Buckaroo\Dependencies\Amp\Parallel\Sync\ChannelException If sending on the channel fails.
     * @throws \Error If an ExitResult object is given.
     * @throws \WC_Buckaroo\Dependencies\Amp\Parallel\Sync\SerializationException If serializing the data fails.
     */
    public function send($data): Promise;
}
