<?php

namespace WC_Buckaroo\Dependencies\Amp\Process\Internal;

use WC_Buckaroo\Dependencies\Amp\Deferred;
use WC_Buckaroo\Dependencies\Amp\Process\ProcessInputStream;
use WC_Buckaroo\Dependencies\Amp\Process\ProcessOutputStream;
use WC_Buckaroo\Dependencies\Amp\Struct;

abstract class ProcessHandle
{
    use Struct;

    /** @var ProcessOutputStream */
    public $stdin;

    /** @var ProcessInputStream */
    public $stdout;

    /** @var ProcessInputStream */
    public $stderr;

    /** @var Deferred */
    public $pidDeferred;

    /** @var int */
    public $status = ProcessStatus::STARTING;
}
