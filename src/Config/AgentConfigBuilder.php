<?php

declare(strict_types=1);

namespace ClaudeAgents\Config;

use Psr\Log\LoggerInterface;

/**
 * Fluent builder for AgentConfig.
 *
 * Provides a type-safe, fluent API for building agent configurations.
 *
 * @example
 * ```php
 * $config = AgentConfigBuilder::create()
 *     ->withModel('claude-opus-4')
 *     ->withMaxTokens(4096)
 *     ->withMaxIterations(10)
 *     ->withSystemPrompt('You are a helpful assistant')
 *     ->withThinking(10000)
 *     ->build();
 * ```
 */
class AgentConfigBuilder
{
    private string $model = AgentConfig::DEFAULT_MODEL;
    private int $maxTokens = AgentConfig::DEFAULT_MAX_TOKENS;
    private int $maxIterations = AgentConfig::DEFAULT_MAX_ITERATIONS;
    private ?string $systemPrompt = null;
    private ?LoggerInterface $logger = null;

    /**
     * @var array<string, mixed>
     */
    private array $thinking = [];

    /**
     * @var array<string, mixed>
     */
    private array $additionalOptions = [];

    /**
     * Create a new builder instance.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Set the model.
     */
    public function withModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Set max tokens.
     */
    public function withMaxTokens(int $maxTokens): self
    {
        $this->maxTokens = $maxTokens;

        return $this;
    }

    /**
     * Set max iterations.
     */
    public function withMaxIterations(int $maxIterations): self
    {
        $this->maxIterations = $maxIterations;

        return $this;
    }

    /**
     * Set system prompt.
     */
    public function withSystemPrompt(string $systemPrompt): self
    {
        $this->systemPrompt = $systemPrompt;

        return $this;
    }

    /**
     * Set logger.
     */
    public function withLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * Enable extended thinking.
     */
    public function withThinking(int $budgetTokens = 10000): self
    {
        $this->thinking = [
            'type' => 'enabled',
            'budget_tokens' => $budgetTokens,
        ];

        return $this;
    }

    /**
     * Disable extended thinking.
     */
    public function withoutThinking(): self
    {
        $this->thinking = [];

        return $this;
    }

    /**
     * Set custom thinking configuration.
     *
     * @param array<string, mixed> $thinking
     */
    public function withThinkingConfig(array $thinking): self
    {
        $this->thinking = $thinking;

        return $this;
    }

    /**
     * Add custom option.
     *
     * @param mixed $value
     */
    public function withOption(string $key, $value): self
    {
        $this->additionalOptions[$key] = $value;

        return $this;
    }

    /**
     * Add multiple custom options.
     *
     * @param array<string, mixed> $options
     */
    public function withOptions(array $options): self
    {
        $this->additionalOptions = array_merge($this->additionalOptions, $options);

        return $this;
    }

    /**
     * Build the AgentConfig.
     */
    public function build(): AgentConfig
    {
        $options = array_merge([
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'max_iterations' => $this->maxIterations,
        ], $this->additionalOptions);

        if ($this->systemPrompt !== null) {
            $options['system_prompt'] = $this->systemPrompt;
        }

        if (! empty($this->thinking)) {
            $options['thinking'] = $this->thinking;
        }

        return AgentConfig::fromArray($options);
    }

    /**
     * Build and return as array (for agents that use array config).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $options = [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'max_iterations' => $this->maxIterations,
        ];

        if ($this->systemPrompt !== null) {
            $options['system'] = $this->systemPrompt;
        }

        if ($this->logger !== null) {
            $options['logger'] = $this->logger;
        }

        if (! empty($this->thinking)) {
            $options['thinking'] = $this->thinking;
        }

        return array_merge($options, $this->additionalOptions);
    }
}
