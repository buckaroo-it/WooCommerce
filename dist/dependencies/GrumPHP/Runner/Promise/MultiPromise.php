<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Runner\Promise;

use WC_Buckaroo\Dependencies\Amp\CancellationTokenSource;
use WC_Buckaroo\Dependencies\Amp\CancelledException;
use WC_Buckaroo\Dependencies\Amp\Deferred;
use WC_Buckaroo\Dependencies\Amp\Loop;
use WC_Buckaroo\Dependencies\Amp\Promise;
use WC_Buckaroo\Dependencies\Amp\LazyPromise;
use function WC_Buckaroo\Dependencies\Amp\Promise\any;

class MultiPromise
{
    /**
     * @template TValue
     *
     * @param array<int, LazyPromise<TValue>> $promises
     * @param callable(TValue):bool $shouldCancel
     *
     * @return Promise<array{0: \Throwable[], 1: TValue[]}>
     */
    public static function cancelable(array $promises, callable $shouldCancel): Promise
    {
        $tokenSource = new CancellationTokenSource();
        return any(
            array_map(
                static function (LazyPromise $promise) use ($tokenSource, $shouldCancel) : Promise {
                    $deferred = new Deferred();

                    Loop::defer(
                        static function (string $watcherId) use (
                            $tokenSource,
                            $shouldCancel,
                            $deferred,
                            $promise
                        ): void {
                            $tokenSource->getToken()->subscribe(
                                static function (CancelledException $error) use ($deferred, $watcherId): void {
                                    $deferred->fail($error);
                                    Loop::cancel($watcherId);
                                }
                            );

                            $cancel = static function (?\Throwable $error = null) use ($tokenSource): void {
                                Loop::defer(function () use ($tokenSource, $error) {
                                    $tokenSource->cancel($error);
                                });
                            };

                            $promise->onResolve(
                                /**
                                 * @param TValue $result
                                 */
                                static function (
                                    ?\Throwable $error,
                                    $result
                                ) use (
                                    $deferred,
                                    $cancel,
                                    $shouldCancel
                                ): void {
                                    if ($error instanceof \Throwable) {
                                        $cancel($error);
                                        $deferred->fail($error);
                                        return;
                                    }

                                    if ($result && $shouldCancel($result)) {
                                        $cancel();
                                    }

                                    $deferred->resolve($result);
                                }
                            );
                        }
                    );

                    return $deferred->promise();
                },
                $promises
            )
        );
    }
}
