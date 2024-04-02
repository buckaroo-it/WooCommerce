<?php

namespace WC_Buckaroo\Dependencies\Amp\Sync\Internal;

use WC_Buckaroo\Dependencies\Amp\Delayed;
use WC_Buckaroo\Dependencies\Amp\Promise;
use WC_Buckaroo\Dependencies\Amp\Sync\Lock;
use function WC_Buckaroo\Dependencies\Amp\call;

/** @internal */
final class MutexStorage extends \Threaded
{
    public const LATENCY_TIMEOUT =  10;

    /** @var bool */
    private $locked = false;

    public function acquire(): Promise
    {
        return call(function (): \Generator {
            $tsl = function (): bool {
                if ($this->locked) {
                    return true;
                }

                $this->locked = true;
                return false;
            };

            while ($this->locked || $this->synchronized($tsl)) {
                yield new Delayed(self::LATENCY_TIMEOUT);
            }

            return new Lock(0, function (): void {
                $this->locked = false;
            });
        });
    }
}
