<?php

declare(strict_types=1);

namespace ClaudeAgents\Templates\Exceptions;

use RuntimeException;

/**
 * Exception thrown when template validation fails.
 */
class TemplateValidationException extends RuntimeException
{
    private array $errors = [];

    public static function withErrors(array $errors): self
    {
        $exception = new self('Template validation failed: ' . implode(', ', $errors));
        $exception->errors = $errors;
        return $exception;
    }

    public static function missingRequired(string $field): self
    {
        return new self("Required field '{$field}' is missing.");
    }

    public static function invalidValue(string $field, string $reason): self
    {
        return new self("Invalid value for field '{$field}': {$reason}");
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
