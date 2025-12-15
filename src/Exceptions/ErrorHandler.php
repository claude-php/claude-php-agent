<?php

declare(strict_types=1);

namespace ClaudeAgents\Exceptions;

use ClaudeAgents\AgentResult;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Unified error handling with retry logic and recovery strategies.
 *
 * Provides consistent error handling across all agents with configurable
 * retry behavior and error classification.
 *
 * @example
 * ```php
 * $handler = new ErrorHandler($logger);
 * $result = $handler->handle(function() {
 *     return $agent->run($task);
 * }, [
 *     'max_retries' => 3,
 *     'retry_delay' => 1000,
 * ]);
 * ```
 */
class ErrorHandler
{
    private const DEFAULT_MAX_RETRIES = 3;
    private const DEFAULT_RETRY_DELAY = 1000; // milliseconds
    private const BACKOFF_MULTIPLIER = 2;

    public function __construct(
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * Handle execution with automatic retry on failure.
     *
     * @param callable $callback Function to execute
     * @param array<string, mixed> $options Retry options
     * @return AgentResult
     */
    public function handle(callable $callback, array $options = []): AgentResult
    {
        $maxRetries = $options['max_retries'] ?? self::DEFAULT_MAX_RETRIES;
        $retryDelay = $options['retry_delay'] ?? self::DEFAULT_RETRY_DELAY;
        $onRetry = $options['on_retry'] ?? null;

        $lastException = null;

        for ($attempt = 1; $attempt <= $maxRetries + 1; $attempt++) {
            try {
                return $callback();
            } catch (\Throwable $e) {
                $lastException = $e;

                if ($attempt > $maxRetries) {
                    break;
                }

                $this->logger->warning("Attempt {$attempt} failed, retrying...", [
                    'error' => $e->getMessage(),
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries,
                ]);

                if ($onRetry !== null) {
                    $onRetry($e, $attempt);
                }

                // Exponential backoff
                $delay = $retryDelay * pow(self::BACKOFF_MULTIPLIER, $attempt - 1);
                usleep((int) ($delay * 1000));
            }
        }

        $this->logger->error('All retry attempts failed', [
            'error' => $lastException?->getMessage(),
            'attempts' => $maxRetries + 1,
        ]);

        return AgentResult::failure(
            error: $lastException?->getMessage() ?? 'Unknown error',
            metadata: [
                'attempts' => $maxRetries + 1,
                'error_type' => $lastException ? get_class($lastException) : 'unknown',
            ]
        );
    }

    /**
     * Check if an error is retryable.
     */
    public function isRetryable(\Throwable $error): bool
    {
        // Network errors, timeouts, rate limits are typically retryable
        return $error instanceof \RuntimeException
            || str_contains($error->getMessage(), 'timeout')
            || str_contains($error->getMessage(), 'rate limit')
            || str_contains($error->getMessage(), 'connection');
    }

    /**
     * Classify error severity.
     */
    public function getErrorSeverity(\Throwable $error): string
    {
        if ($error instanceof ConfigurationException) {
            return 'critical';
        }

        if ($error instanceof ValidationException) {
            return 'error';
        }

        if ($error instanceof ParseException) {
            return 'warning';
        }

        return 'error';
    }
}
