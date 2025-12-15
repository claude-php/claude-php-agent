<?php

declare(strict_types=1);

namespace ClaudeAgents\Helpers;

use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Circuit Breaker Pattern Implementation
 *
 * Prevents cascading failures by temporarily disabling failing operations.
 * When a threshold of failures is reached, the circuit "opens" and stops
 * attempting the operation for a timeout period.
 *
 * States:
 * - CLOSED: Normal operation, requests pass through
 * - OPEN: Too many failures, requests fail immediately
 * - HALF_OPEN: Testing if service recovered, limited requests allowed
 */
class CircuitBreaker
{
    private const STATE_CLOSED = 'closed';
    private const STATE_OPEN = 'open';
    private const STATE_HALF_OPEN = 'half_open';

    private string $state = self::STATE_CLOSED;
    private int $failureCount = 0;
    private int $successCount = 0;
    private ?int $openedAt = null;
    private LoggerInterface $logger;

    /**
     * @param string $name Circuit breaker identifier
     * @param int $failureThreshold Number of failures before opening
     * @param int $timeoutSeconds How long to wait before testing recovery
     * @param int $successThreshold Successes needed in half-open to close
     * @param LoggerInterface|null $logger Optional logger
     */
    public function __construct(
        private string $name,
        private int $failureThreshold = 5,
        private int $timeoutSeconds = 60,
        private int $successThreshold = 2,
        ?LoggerInterface $logger = null
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->logger->info("Circuit breaker '{$name}' initialized", [
            'failure_threshold' => $failureThreshold,
            'timeout' => $timeoutSeconds,
            'success_threshold' => $successThreshold,
        ]);
    }

    /**
     * Execute a function with circuit breaker protection
     *
     * @param callable $fn Function to execute
     * @throws CircuitBreakerOpenException if circuit is open
     * @throws Exception if function fails
     * @return mixed Result from function
     */
    public function call(callable $fn): mixed
    {
        if ($this->isOpen()) {
            $this->logger->warning("Circuit breaker '{$this->name}' is OPEN, rejecting call");

            throw new CircuitBreakerOpenException(
                "Circuit breaker '{$this->name}' is open. Service temporarily unavailable."
            );
        }

        try {
            $result = $fn();
            $this->recordSuccess();

            return $result;
        } catch (Exception $e) {
            $this->recordFailure();

            throw $e;
        }
    }

    /**
     * Check if circuit breaker is open
     *
     * @return bool True if open or should transition to half-open
     */
    private function isOpen(): bool
    {
        if ($this->state === self::STATE_CLOSED) {
            return false;
        }

        if ($this->state === self::STATE_OPEN) {
            // Check if timeout expired - transition to half-open
            if ($this->openedAt !== null && (time() - $this->openedAt) >= $this->timeoutSeconds) {
                $this->transitionToHalfOpen();

                return false; // Allow requests in half-open state
            }

            return true;
        }

        // STATE_HALF_OPEN - allow requests
        return false;
    }

    /**
     * Record a successful execution
     */
    private function recordSuccess(): void
    {
        if ($this->state === self::STATE_HALF_OPEN) {
            $this->successCount++;

            $this->logger->debug("Circuit breaker '{$this->name}' success in HALF_OPEN ({$this->successCount}/{$this->successThreshold})");

            if ($this->successCount >= $this->successThreshold) {
                $this->transitionToClosed();
            }
        } elseif ($this->state === self::STATE_CLOSED) {
            // Reset failure count on success
            if ($this->failureCount > 0) {
                $this->logger->debug("Circuit breaker '{$this->name}' success, resetting failure count");
                $this->failureCount = 0;
            }
        }
    }

    /**
     * Record a failed execution
     */
    private function recordFailure(): void
    {
        $this->failureCount++;

        if ($this->state === self::STATE_HALF_OPEN) {
            $this->logger->warning("Circuit breaker '{$this->name}' failure in HALF_OPEN, re-opening");
            $this->transitionToOpen();
        } elseif ($this->state === self::STATE_CLOSED) {
            $this->logger->warning("Circuit breaker '{$this->name}' failure {$this->failureCount}/{$this->failureThreshold}");

            if ($this->failureCount >= $this->failureThreshold) {
                $this->transitionToOpen();
            }
        }
    }

    /**
     * Transition to OPEN state
     */
    private function transitionToOpen(): void
    {
        $this->state = self::STATE_OPEN;
        $this->openedAt = time();
        $this->successCount = 0;

        $this->logger->error("Circuit breaker '{$this->name}' OPENED after {$this->failureCount} failures", [
            'failure_count' => $this->failureCount,
            'timeout' => $this->timeoutSeconds,
        ]);
    }

    /**
     * Transition to HALF_OPEN state
     */
    private function transitionToHalfOpen(): void
    {
        $this->state = self::STATE_HALF_OPEN;
        $this->successCount = 0;
        $this->failureCount = 0;

        $this->logger->info("Circuit breaker '{$this->name}' transitioning to HALF_OPEN, testing recovery");
    }

    /**
     * Transition to CLOSED state
     */
    private function transitionToClosed(): void
    {
        $this->state = self::STATE_CLOSED;
        $this->failureCount = 0;
        $this->successCount = 0;
        $this->openedAt = null;

        $this->logger->info("Circuit breaker '{$this->name}' CLOSED, service recovered");
    }

    /**
     * Get current state
     *
     * @return string Current state (closed, open, half_open)
     */
    public function getState(): string
    {
        // Update state if needed
        $this->isOpen();

        return $this->state;
    }

    /**
     * Get current statistics
     *
     * @return array Statistics array
     */
    public function getStats(): array
    {
        return [
            'name' => $this->name,
            'state' => $this->getState(),
            'failure_count' => $this->failureCount,
            'success_count' => $this->successCount,
            'failure_threshold' => $this->failureThreshold,
            'timeout_seconds' => $this->timeoutSeconds,
            'opened_at' => $this->openedAt,
            'time_until_retry' => $this->openedAt
                ? max(0, $this->timeoutSeconds - (time() - $this->openedAt))
                : null,
        ];
    }

    /**
     * Manually reset the circuit breaker to closed state
     */
    public function reset(): void
    {
        $this->logger->info("Circuit breaker '{$this->name}' manually reset");
        $this->transitionToClosed();
    }

    /**
     * Manually open the circuit breaker
     */
    public function forceOpen(): void
    {
        $this->logger->warning("Circuit breaker '{$this->name}' manually opened");
        $this->transitionToOpen();
    }
}

/**
 * Exception thrown when circuit breaker is open
 */
class CircuitBreakerOpenException extends Exception
{
    public function __construct(string $message = 'Circuit breaker is open')
    {
        parent::__construct($message);
    }
}
