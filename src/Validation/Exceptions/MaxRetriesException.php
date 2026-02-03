<?php

declare(strict_types=1);

namespace ClaudeAgents\Validation\Exceptions;

use RuntimeException;

/**
 * Exception thrown when maximum validation retries are exceeded.
 */
class MaxRetriesException extends ValidationException
{
    /**
     * @param array<string> $lastErrors
     */
    public function __construct(
        int $maxRetries,
        private readonly array $lastErrors = [],
        ?\Throwable $previous = null,
    ) {
        $message = "Maximum validation retries ({$maxRetries}) exceeded";
        parent::__construct($message, $lastErrors, 0, $previous);
    }

    /**
     * Get the last validation errors before giving up.
     *
     * @return array<string>
     */
    public function getLastErrors(): array
    {
        return $this->lastErrors;
    }
}
