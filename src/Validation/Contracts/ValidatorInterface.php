<?php

declare(strict_types=1);

namespace ClaudeAgents\Validation\Contracts;

use ClaudeAgents\Validation\ValidationResult;

/**
 * Interface for code validators.
 *
 * Validators can check code for syntax errors, static analysis issues,
 * best practices, or any custom validation rules.
 */
interface ValidatorInterface
{
    /**
     * Validate the given code.
     *
     * @param string $code The code to validate
     * @param array<string, mixed> $context Additional context for validation
     * @return ValidationResult The validation result
     */
    public function validate(string $code, array $context = []): ValidationResult;

    /**
     * Get the validator name.
     *
     * @return string Unique identifier for this validator
     */
    public function getName(): string;

    /**
     * Check if this validator can handle the given code.
     *
     * @param string $code The code to check
     * @return bool True if this validator can validate the code
     */
    public function canHandle(string $code): bool;

    /**
     * Get the priority of this validator.
     * Lower numbers run first (e.g., 10 before 100).
     *
     * @return int Priority value
     */
    public function getPriority(): int;
}
