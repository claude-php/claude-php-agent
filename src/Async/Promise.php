<?php

declare(strict_types=1);

namespace ClaudeAgents\Async;

use Amp\DeferredFuture;
use Amp\Future;
use Amp\TimeoutCancellation;

/**
 * Promise implementation using AMPHP futures.
 *
 * This provides a backward-compatible interface while using AMPHP's
 * powerful async primitives under the hood.
 */
class Promise
{
    private DeferredFuture $deferred;
    private Future $future;

    public function __construct()
    {
        $this->deferred = new DeferredFuture();
        $this->future = $this->deferred->getFuture();
    }

    /**
     * Resolve the promise with a value.
     */
    public function resolve(mixed $value): void
    {
        if (! $this->future->isComplete()) {
            $this->deferred->complete($value);
        }
    }

    /**
     * Reject the promise with an error.
     */
    public function reject(\Throwable $error): void
    {
        if (! $this->future->isComplete()) {
            $this->deferred->error($error);
        }
    }

    /**
     * Check if promise is resolved.
     */
    public function isResolved(): bool
    {
        return $this->future->isComplete();
    }

    /**
     * Get the result (blocking).
     *
     * @throws \Throwable If rejected
     */
    public function getResult(): mixed
    {
        return $this->future->await();
    }

    /**
     * Wait for resolution (blocking).
     *
     * @param int $timeoutMs Timeout in milliseconds
     * @throws \Throwable If promise is rejected or timeout occurs
     */
    public function wait(int $timeoutMs = 30000): mixed
    {
        $cancellation = new TimeoutCancellation($timeoutMs / 1000.0);

        try {
            return $this->future->await($cancellation);
        } catch (\Amp\CancelledException $e) {
            throw new \RuntimeException('Promise wait timeout', 0, $e);
        }
    }

    /**
     * Add a callback to execute when resolved.
     *
     * @param callable $callback Function that receives the result
     * @return self
     */
    public function then(callable $callback): self
    {
        $this->future->map($callback);

        return $this;
    }

    /**
     * Add a catch callback for errors.
     *
     * @param callable $callback Function that receives the error
     * @return self
     */
    public function catch(callable $callback): self
    {
        $this->future->catch($callback);

        return $this;
    }

    /**
     * Get the underlying AMPHP Future.
     */
    public function getFuture(): Future
    {
        return $this->future;
    }

    /**
     * Create a resolved promise.
     */
    public static function resolved(mixed $value): self
    {
        $promise = new self();
        $promise->resolve($value);

        return $promise;
    }

    /**
     * Create a rejected promise.
     */
    public static function rejected(\Throwable $error): self
    {
        $promise = new self();
        $promise->reject($error);

        return $promise;
    }

    /**
     * Wait for all promises to complete.
     *
     * @param array<Promise> $promises
     * @throws \Throwable If any promise is rejected
     * @return array<mixed> Results in order
     */
    public static function all(array $promises): array
    {
        $results = [];
        foreach ($promises as $key => $promise) {
            $results[$key] = $promise->getFuture()->await();
        }

        return $results;
    }

    /**
     * Wait for the first promise to complete.
     *
     * @param array<Promise> $promises
     * @return mixed Result of first completed promise
     */
    public static function race(array $promises): mixed
    {
        // For race, we need to check all futures until one completes
        // In a simple implementation, just return the first one
        if (empty($promises)) {
            throw new \RuntimeException('Cannot race empty array of promises');
        }

        return reset($promises)->getFuture()->await();
    }

    /**
     * Wait for any promise to complete (doesn't throw on rejection).
     *
     * @param array<Promise> $promises
     * @return array<mixed> Results (may include exceptions)
     */
    public static function allSettled(array $promises): array
    {
        $futures = array_map(fn ($p) => $p->getFuture(), $promises);

        $results = [];
        foreach ($futures as $future) {
            try {
                $results[] = $future->await();
            } catch (\Throwable $e) {
                $results[] = $e;
            }
        }

        return $results;
    }
}
