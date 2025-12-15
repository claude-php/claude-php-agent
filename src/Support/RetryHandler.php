<?php

declare(strict_types=1);

namespace ClaudeAgents\Support;

use ClaudeAgents\Config\RetryConfig;
use ClaudeAgents\Exceptions\RetryException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Handles retry logic with exponential backoff.
 */
class RetryHandler
{
    private RetryConfig $config;
    private LoggerInterface $logger;

    /**
     * @var callable|null
     */
    private $onRetry = null;

    /**
     * @var array<string> Exception classes that should trigger a retry
     */
    private array $retryableExceptions = [];

    public function __construct(
        ?RetryConfig $config = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->config = $config ?? new RetryConfig();
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Set callback for retry events.
     *
     * @param callable $callback fn(int $attempt, \Throwable $exception)
     */
    public function onRetry(callable $callback): self
    {
        $this->onRetry = $callback;

        return $this;
    }

    /**
     * Add exception classes that should trigger retries.
     *
     * @param string ...$exceptionClasses
     */
    public function retryOn(string ...$exceptionClasses): self
    {
        $this->retryableExceptions = array_merge(
            $this->retryableExceptions,
            $exceptionClasses
        );

        return $this;
    }

    /**
     * Execute a callable with retry logic.
     *
     * @template T
     * @param callable(): T $callable
     * @throws \Throwable If all retries fail
     * @return T
     */
    public function execute(callable $callable): mixed
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->config->getMaxAttempts()) {
            $attempt++;

            try {
                return $callable();
            } catch (\Throwable $e) {
                $lastException = $e;

                // Check if this exception is retryable
                if (! $this->isRetryable($e)) {
                    throw $e;
                }

                // Check if we've exhausted retries
                if ($attempt >= $this->config->getMaxAttempts()) {
                    $this->logger->error('All retry attempts exhausted', [
                        'attempts' => $attempt,
                        'exception' => $e->getMessage(),
                    ]);

                    throw $e;
                }

                // Calculate delay
                $delay = $this->config->getDelayForAttempt($attempt);

                $this->logger->warning('Retrying after failure', [
                    'attempt' => $attempt,
                    'delay_ms' => $delay,
                    'exception' => $e->getMessage(),
                ]);

                // Fire retry callback
                if ($this->onRetry !== null) {
                    ($this->onRetry)($attempt, $e);
                }

                // Wait before retrying
                usleep($delay * 1000);
            }
        }

        // All retries exhausted
        throw new RetryException(
            'All retry attempts exhausted',
            $attempt,
            $this->config->getMaxAttempts(),
            $lastException
        );
    }

    /**
     * Check if an exception should trigger a retry.
     */
    private function isRetryable(\Throwable $e): bool
    {
        // If no specific exceptions configured, retry all
        if (empty($this->retryableExceptions)) {
            return true;
        }

        foreach ($this->retryableExceptions as $exceptionClass) {
            if ($e instanceof $exceptionClass) {
                return true;
            }
        }

        return false;
    }
}
