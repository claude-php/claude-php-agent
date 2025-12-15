<?php

declare(strict_types=1);

namespace ClaudeAgents\Config;

use ClaudeAgents\Exceptions\ConfigurationException;

/**
 * Configuration for chain execution.
 */
class ChainConfig
{
    public const DEFAULT_TIMEOUT_MS = 30000;
    public const DEFAULT_AGGREGATION = 'merge';
    public const DEFAULT_ERROR_POLICY = 'stop';

    /**
     * @param int $timeoutMs Timeout in milliseconds for chain execution
     * @param string $aggregation Aggregation strategy: 'merge', 'first', 'all'
     * @param string $errorPolicy Error handling: 'stop', 'continue', 'collect'
     * @param bool $stopOnFirstSuccess Stop on first successful result (parallel chains)
     * @param bool $validateInputs Validate inputs against schema
     * @param bool $validateOutputs Validate outputs against schema
     * @param int $maxRetries Maximum retries for failed chains
     * @param array<string, mixed> $metadata Additional metadata
     */
    public function __construct(
        private readonly int $timeoutMs = self::DEFAULT_TIMEOUT_MS,
        private readonly string $aggregation = self::DEFAULT_AGGREGATION,
        private readonly string $errorPolicy = self::DEFAULT_ERROR_POLICY,
        private readonly bool $stopOnFirstSuccess = false,
        private readonly bool $validateInputs = true,
        private readonly bool $validateOutputs = true,
        private readonly int $maxRetries = 0,
        private readonly array $metadata = [],
    ) {
        $this->validateAggregation();
        $this->validateErrorPolicy();
    }

    /**
     * Create from array configuration.
     *
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            timeoutMs: $config['timeout_ms'] ?? $config['timeout'] ?? self::DEFAULT_TIMEOUT_MS,
            aggregation: $config['aggregation'] ?? self::DEFAULT_AGGREGATION,
            errorPolicy: $config['error_policy'] ?? self::DEFAULT_ERROR_POLICY,
            stopOnFirstSuccess: $config['stop_on_first_success'] ?? false,
            validateInputs: $config['validate_inputs'] ?? true,
            validateOutputs: $config['validate_outputs'] ?? true,
            maxRetries: $config['max_retries'] ?? 0,
            metadata: $config['metadata'] ?? [],
        );
    }

    public function getTimeoutMs(): int
    {
        return $this->timeoutMs;
    }

    public function getTimeoutSeconds(): float
    {
        return $this->timeoutMs / 1000.0;
    }

    public function getAggregation(): string
    {
        return $this->aggregation;
    }

    public function getErrorPolicy(): string
    {
        return $this->errorPolicy;
    }

    public function shouldStopOnFirstSuccess(): bool
    {
        return $this->stopOnFirstSuccess;
    }

    public function shouldValidateInputs(): bool
    {
        return $this->validateInputs;
    }

    public function shouldValidateOutputs(): bool
    {
        return $this->validateOutputs;
    }

    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Create a new config with modified values.
     *
     * @param array<string, mixed> $overrides
     */
    public function with(array $overrides): self
    {
        return new self(
            timeoutMs: $overrides['timeout_ms'] ?? $overrides['timeout'] ?? $this->timeoutMs,
            aggregation: $overrides['aggregation'] ?? $this->aggregation,
            errorPolicy: $overrides['error_policy'] ?? $this->errorPolicy,
            stopOnFirstSuccess: $overrides['stop_on_first_success'] ?? $this->stopOnFirstSuccess,
            validateInputs: $overrides['validate_inputs'] ?? $this->validateInputs,
            validateOutputs: $overrides['validate_outputs'] ?? $this->validateOutputs,
            maxRetries: $overrides['max_retries'] ?? $this->maxRetries,
            metadata: $overrides['metadata'] ?? $this->metadata,
        );
    }

    /**
     * Check if error policy is to stop on first error.
     */
    public function shouldStopOnError(): bool
    {
        return $this->errorPolicy === 'stop';
    }

    /**
     * Check if error policy is to continue on errors.
     */
    public function shouldContinueOnError(): bool
    {
        return $this->errorPolicy === 'continue';
    }

    /**
     * Check if error policy is to collect errors.
     */
    public function shouldCollectErrors(): bool
    {
        return $this->errorPolicy === 'collect';
    }

    private function validateAggregation(): void
    {
        $valid = ['merge', 'first', 'all'];
        if (! in_array($this->aggregation, $valid, true)) {
            throw new ConfigurationException(
                "Invalid aggregation strategy: {$this->aggregation}. Must be one of: " . implode(', ', $valid),
                'aggregation',
                $this->aggregation
            );
        }
    }

    private function validateErrorPolicy(): void
    {
        $valid = ['stop', 'continue', 'collect'];
        if (! in_array($this->errorPolicy, $valid, true)) {
            throw new ConfigurationException(
                "Invalid error policy: {$this->errorPolicy}. Must be one of: " . implode(', ', $valid),
                'error_policy',
                $this->errorPolicy
            );
        }
    }
}
