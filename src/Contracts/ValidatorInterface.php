<?php

declare(strict_types=1);

namespace ClaudeAgents\Contracts;

/**
 * Interface for input/output validation.
 *
 * Validators ensure data meets expected schemas, formats, and business rules
 * before being processed by agents or chains.
 */
interface ValidatorInterface
{
    /**
     * Validate data against rules or schema.
     *
     * @param mixed $data The data to validate
     * @param array<string, mixed> $rules Optional validation rules or schema
     * @throws \InvalidArgumentException When validation fails
     * @return bool True if valid
     */
    public function validate(mixed $data, array $rules = []): bool;

    /**
     * Get validation errors from the last validation attempt.
     *
     * @return array<string> Array of error messages
     */
    public function getErrors(): array;

    /**
     * Check if data is valid without throwing exceptions.
     *
     * @param mixed $data The data to validate
     * @param array<string, mixed> $rules Optional validation rules or schema
     * @return bool True if valid, false otherwise
     */
    public function isValid(mixed $data, array $rules = []): bool;

    /**
     * Add a custom validation rule.
     *
     * @param string $name Rule name
     * @param callable $callback Validation function fn(mixed $value): bool
     */
    public function addRule(string $name, callable $callback): void;

    /**
     * Get the validation schema.
     *
     * @return array<string, mixed>
     */
    public function getSchema(): array;
}
