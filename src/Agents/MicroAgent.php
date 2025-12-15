<?php

declare(strict_types=1);

namespace ClaudeAgents\Agents;

use ClaudeAgents\Exceptions\RetryException;
use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Micro Agent for MAKER framework.
 *
 * A minimal agent designed to execute a single, focused subtask.
 * These agents are the atomic units of the Massively Decomposed Agentic Process.
 *
 * Each micro-agent:
 * - Has a specific role (decomposer, executor, composer)
 * - Executes independently without dependencies
 * - Produces deterministic outputs for the same input
 * - Is lightweight and fast
 */
class MicroAgent
{
    private ClaudePhp $client;
    private string $role;
    private string $model;
    private int $maxTokens;
    private float $temperature;
    private LoggerInterface $logger;
    private array $systemPrompts = [
        'decomposer' => 'You are a precise task decomposer. Break tasks into minimal, clear subtasks.',
        'executor' => 'You are a focused executor. Execute tasks precisely and concisely.',
        'composer' => 'You are a result composer. Synthesize subtask results coherently.',
        'validator' => 'You are a validator. Verify that results meet requirements.',
        'discriminator' => 'You are a discriminator. Choose the best solution from alternatives.',
    ];

    /**
     * @param ClaudePhp $client The Claude API client
     * @param array<string, mixed> $options Configuration:
     *   - role: The micro-agent's role (decomposer, executor, composer, validator, discriminator)
     *   - model: Model to use
     *   - max_tokens: Max tokens per response
     *   - temperature: Sampling temperature (default: 0.1 for consistency)
     *   - logger: PSR-3 logger
     */
    public function __construct(ClaudePhp $client, array $options = [])
    {
        $this->client = $client;
        $this->role = $options['role'] ?? 'executor';
        $this->model = $options['model'] ?? 'claude-sonnet-4-5';
        $this->maxTokens = $options['max_tokens'] ?? 2048;
        $this->temperature = $options['temperature'] ?? 0.1; // Low temperature for consistency
        $this->logger = $options['logger'] ?? new NullLogger();
    }

    /**
     * Execute the micro-agent's task.
     */
    public function execute(string $prompt): string
    {
        $this->logger->debug("MicroAgent [{$this->role}] executing", [
            'prompt_length' => strlen($prompt),
        ]);

        try {
            $response = $this->client->messages()->create([
                'model' => $this->model,
                'max_tokens' => $this->maxTokens,
                'temperature' => $this->temperature,
                'system' => $this->getSystemPrompt(),
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

            $result = $this->extractTextContent($response->content ?? []);

            $this->logger->debug("MicroAgent [{$this->role}] completed", [
                'response_length' => strlen($result),
                'input_tokens' => $response->usage->input_tokens ?? 0,
                'output_tokens' => $response->usage->output_tokens ?? 0,
            ]);

            return $result;
        } catch (\Throwable $e) {
            $this->logger->error("MicroAgent [{$this->role}] failed: {$e->getMessage()}");

            throw $e;
        }
    }

    /**
     * Execute with retries for reliability.
     */
    public function executeWithRetry(string $prompt, int $maxRetries = 3): string
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                return $this->execute($prompt);
            } catch (\Throwable $e) {
                $this->logger->warning("MicroAgent [{$this->role}] attempt {$attempt} failed", [
                    'error' => $e->getMessage(),
                ]);
                $lastException = $e;

                if ($attempt < $maxRetries) {
                    // Exponential backoff
                    usleep(100000 * pow(2, $attempt - 1)); // 0.1s, 0.2s, 0.4s
                }
            }
        }

        throw new RetryException(
            "MicroAgent [{$this->role}] failed after all retry attempts",
            $maxRetries,
            $maxRetries,
            $lastException
        );
    }

    /**
     * Get the system prompt for this micro-agent's role.
     */
    private function getSystemPrompt(): string
    {
        return $this->systemPrompts[$this->role] ?? $this->systemPrompts['executor'];
    }

    /**
     * Extract text content from response blocks.
     *
     * @param array<mixed> $content
     */
    private function extractTextContent(array $content): string
    {
        $texts = [];

        foreach ($content as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'text') {
                $texts[] = $block['text'] ?? '';
            }
        }

        return implode("\n", $texts);
    }

    /**
     * Get the micro-agent's role.
     */
    public function getRole(): string
    {
        return $this->role;
    }

    /**
     * Set custom system prompt for this micro-agent.
     */
    public function setSystemPrompt(string $prompt): self
    {
        $this->systemPrompts[$this->role] = $prompt;

        return $this;
    }
}
