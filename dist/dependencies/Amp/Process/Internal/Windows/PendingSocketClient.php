<?php

namespace WC_Buckaroo\Dependencies\Amp\Process\Internal\Windows;

use WC_Buckaroo\Dependencies\Amp\Struct;

/**
 * @internal
 * @codeCoverageIgnore Windows only.
 */
final class PendingSocketClient
{
    use Struct;

    public $readWatcher;
    public $timeoutWatcher;
    public $receivedDataBuffer = '';
    public $pid;
    public $streamId;
}
