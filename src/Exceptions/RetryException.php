<?php

declare(strict_types=1);

namespace ClaudeAgents\Exceptions;

/**
 * Thrown when retry attempts are exhausted.
 */
class RetryException extends AgentException
{
    public function __construct(
        string $message,
        int $attempts = 0,
        int $maxAttempts = 0,
        ?\Throwable $lastException = null,
    ) {
        $context = [];
        if ($attempts > 0) {
            $context['attempts'] = $attempts;
        }
        if ($maxAttempts > 0) {
            $context['max_attempts'] = $maxAttempts;
        }
        if ($lastException !== null) {
            $context['last_error'] = $lastException->getMessage();
        }

        parent::__construct(
            $message,
            0,
            $lastException,
            $context,
        );
    }
}
