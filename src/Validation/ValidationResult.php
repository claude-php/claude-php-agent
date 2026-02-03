<?php

declare(strict_types=1);

namespace ClaudeAgents\Validation;

/**
 * Result of a validation operation.
 *
 * Contains success/failure status, errors, warnings, and metadata.
 */
class ValidationResult
{
    /**
     * @param bool $isValid Whether validation passed
     * @param array<string> $errors Validation error messages
     * @param array<string> $warnings Warning messages (non-blocking)
     * @param array<string, mixed> $metadata Additional metadata about validation
     */
    public function __construct(
        private readonly bool $isValid,
        private readonly array $errors = [],
        private readonly array $warnings = [],
        private readonly array $metadata = [],
    ) {
    }

    /**
     * Check if validation passed.
     */
    public function isValid(): bool
    {
        return $this->isValid;
    }

    /**
     * Check if validation failed.
     */
    public function isFailed(): bool
    {
        return ! $this->isValid;
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
     * Get validation warnings.
     *
     * @return array<string>
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Get validation metadata.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Check if there are any errors.
     */
    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    /**
     * Check if there are any warnings.
     */
    public function hasWarnings(): bool
    {
        return count($this->warnings) > 0;
    }

    /**
     * Get error count.
     */
    public function getErrorCount(): int
    {
        return count($this->errors);
    }

    /**
     * Get warning count.
     */
    public function getWarningCount(): int
    {
        return count($this->warnings);
    }

    /**
     * Merge another validation result with this one.
     *
     * The combined result is valid only if both are valid.
     */
    public function merge(ValidationResult $other): self
    {
        return new self(
            isValid: $this->isValid && $other->isValid,
            errors: array_merge($this->errors, $other->errors),
            warnings: array_merge($this->warnings, $other->warnings),
            metadata: array_merge($this->metadata, $other->metadata),
        );
    }

    /**
     * Create a successful validation result.
     *
     * @param array<string> $warnings Optional warnings
     * @param array<string, mixed> $metadata Optional metadata
     */
    public static function success(array $warnings = [], array $metadata = []): self
    {
        return new self(
            isValid: true,
            errors: [],
            warnings: $warnings,
            metadata: $metadata,
        );
    }

    /**
     * Create a failed validation result.
     *
     * @param array<string> $errors Error messages
     * @param array<string> $warnings Optional warnings
     * @param array<string, mixed> $metadata Optional metadata
     */
    public static function failure(array $errors, array $warnings = [], array $metadata = []): self
    {
        return new self(
            isValid: false,
            errors: $errors,
            warnings: $warnings,
            metadata: $metadata,
        );
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->isValid,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'metadata' => $this->metadata,
            'error_count' => $this->getErrorCount(),
            'warning_count' => $this->getWarningCount(),
        ];
    }

    /**
     * Convert to JSON string.
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }

    /**
     * Get a summary message.
     */
    public function getSummary(): string
    {
        if ($this->isValid) {
            $msg = 'Validation passed';
            if ($this->hasWarnings()) {
                $msg .= ' with ' . $this->getWarningCount() . ' warning(s)';
            }
            return $msg;
        }

        return 'Validation failed with ' . $this->getErrorCount() . ' error(s)';
    }
}
