<?php

declare(strict_types=1);

namespace ClaudeAgents\Config;

/**
 * Configuration for agent execution.
 */
class AgentConfig
{
    public const DEFAULT_MODEL = 'claude-sonnet-4-5';
    public const DEFAULT_MAX_TOKENS = 4096;
    public const DEFAULT_MAX_ITERATIONS = 10;
    public const DEFAULT_TIMEOUT = 30.0;

    /**
     * @param string $model The Claude model to use
     * @param int $maxTokens Maximum tokens per response
     * @param int $maxIterations Maximum loop iterations
     * @param float $timeout Request timeout in seconds
     * @param float|null $temperature Temperature for responses
     * @param string|null $systemPrompt System prompt for the agent
     * @param RetryConfig $retry Retry configuration
     * @param array<string, mixed> $thinking Extended thinking configuration
     */
    public function __construct(
        private readonly string $model = self::DEFAULT_MODEL,
        private readonly int $maxTokens = self::DEFAULT_MAX_TOKENS,
        private readonly int $maxIterations = self::DEFAULT_MAX_ITERATIONS,
        private readonly float $timeout = self::DEFAULT_TIMEOUT,
        private readonly ?float $temperature = null,
        private readonly ?string $systemPrompt = null,
        private readonly RetryConfig $retry = new RetryConfig(),
        private readonly array $thinking = [],
    ) {
    }

    /**
     * Create from array configuration.
     *
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $retryConfig = isset($config['retry']) && is_array($config['retry'])
            ? RetryConfig::fromArray($config['retry'])
            : new RetryConfig();

        return new self(
            model: $config['model'] ?? self::DEFAULT_MODEL,
            maxTokens: $config['max_tokens'] ?? self::DEFAULT_MAX_TOKENS,
            maxIterations: $config['max_iterations'] ?? self::DEFAULT_MAX_ITERATIONS,
            timeout: $config['timeout'] ?? self::DEFAULT_TIMEOUT,
            temperature: $config['temperature'] ?? null,
            systemPrompt: $config['system'] ?? $config['system_prompt'] ?? null,
            retry: $retryConfig,
            thinking: $config['thinking'] ?? [],
        );
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getMaxTokens(): int
    {
        return $this->maxTokens;
    }

    public function getMaxIterations(): int
    {
        return $this->maxIterations;
    }

    public function getTimeout(): float
    {
        return $this->timeout;
    }

    public function getTemperature(): ?float
    {
        return $this->temperature;
    }

    public function getSystemPrompt(): ?string
    {
        return $this->systemPrompt;
    }

    public function getRetry(): RetryConfig
    {
        return $this->retry;
    }

    public function getThinking(): array
    {
        return $this->thinking;
    }

    public function hasThinking(): bool
    {
        return ! empty($this->thinking);
    }

    /**
     * Create a new config with modified values.
     *
     * @param array<string, mixed> $overrides
     */
    public function with(array $overrides): self
    {
        return new self(
            model: $overrides['model'] ?? $this->model,
            maxTokens: $overrides['max_tokens'] ?? $this->maxTokens,
            maxIterations: $overrides['max_iterations'] ?? $this->maxIterations,
            timeout: $overrides['timeout'] ?? $this->timeout,
            temperature: $overrides['temperature'] ?? $this->temperature,
            systemPrompt: $overrides['system_prompt'] ?? $this->systemPrompt,
            retry: isset($overrides['retry']) ? RetryConfig::fromArray($overrides['retry']) : $this->retry,
            thinking: $overrides['thinking'] ?? $this->thinking,
        );
    }

    /**
     * Convert to array for API calls.
     *
     * @return array<string, mixed>
     */
    public function toApiParams(): array
    {
        $params = [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
        ];

        if ($this->systemPrompt !== null) {
            $params['system'] = $this->systemPrompt;
        }

        if ($this->temperature !== null) {
            $params['temperature'] = $this->temperature;
        }

        if ($this->hasThinking()) {
            $params['thinking'] = $this->thinking;
        }

        return $params;
    }
}
