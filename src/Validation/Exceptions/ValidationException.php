<?php

declare(strict_types=1);

namespace ClaudeAgents\Validation\Exceptions;

use RuntimeException;

/**
 * Exception thrown when validation fails.
 */
class ValidationException extends RuntimeException
{
    /**
     * @param array<string> $errors
     */
    public function __construct(
        string $message = 'Validation failed',
        private readonly array $errors = [],
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get validation errors.
     *
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Create from validation result.
     */
    public static function fromErrors(array $errors): self
    {
        $message = 'Validation failed: ' . implode('; ', array_slice($errors, 0, 3));
        if (count($errors) > 3) {
            $message .= '... and ' . (count($errors) - 3) . ' more';
        }

        return new self($message, $errors);
    }
}
