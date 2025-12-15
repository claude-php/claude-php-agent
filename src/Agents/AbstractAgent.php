<?php

declare(strict_types=1);

namespace ClaudeAgents\Agents;

use ClaudeAgents\Contracts\AgentInterface;
use ClaudeAgents\Support\TextContentExtractor;
use ClaudeAgents\Support\TokenUsageFormatter;
use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Abstract base agent providing common functionality for all agent implementations.
 *
 * This class reduces code duplication and enforces consistent patterns across agents,
 * following the DRY principle and Single Responsibility Principle.
 *
 * Subclasses only need to implement the run() method for their specific logic.
 */
abstract class AbstractAgent implements AgentInterface
{
    protected ClaudePhp $client;
    protected string $name;
    protected string $model;
    protected int $maxTokens;
    protected LoggerInterface $logger;

    /**
     * Default configuration values.
     */
    protected const DEFAULT_MODEL = 'claude-sonnet-4-5';
    protected const DEFAULT_MAX_TOKENS = 2048;
    protected const DEFAULT_NAME = 'agent';

    /**
     * Initialize the abstract agent with common configuration.
     *
     * @param ClaudePhp $client The Claude API client
     * @param array<string, mixed> $options Configuration options:
     *   - name: Agent name (default: class-specific)
     *   - model: Model to use (default: claude-sonnet-4-5)
     *   - max_tokens: Max tokens per response (default: 2048)
     *   - logger: PSR-3 logger (default: NullLogger)
     */
    public function __construct(ClaudePhp $client, array $options = [])
    {
        $this->client = $client;
        $this->name = $options['name'] ?? $this->getDefaultName();
        $this->model = $options['model'] ?? static::DEFAULT_MODEL;
        $this->maxTokens = $options['max_tokens'] ?? static::DEFAULT_MAX_TOKENS;
        $this->logger = $options['logger'] ?? new NullLogger();

        $this->initialize($options);
    }

    /**
     * Get the agent's name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Extract text content from response blocks using the shared utility.
     *
     * @param array<mixed>|object $content Response content blocks or response object
     * @return string Combined text content
     */
    protected function extractTextContent(array|object $content): string
    {
        if (is_object($content)) {
            return TextContentExtractor::extractFromResponse($content);
        }

        return TextContentExtractor::extract($content);
    }

    /**
     * Format token usage from a response.
     *
     * @param object|array<mixed> $response Response object or array
     * @return array{input: int, output: int, total: int}
     */
    protected function formatTokenUsage(object|array $response): array
    {
        return TokenUsageFormatter::format($response);
    }

    /**
     * Format simple token usage (input/output only).
     *
     * @param object|array<mixed> $response Response object or array
     * @return array{input: int, output: int}
     */
    protected function formatSimpleTokenUsage(object|array $response): array
    {
        return TokenUsageFormatter::formatSimple($response);
    }

    /**
     * Get the default name for this agent type.
     * Subclasses can override to provide specific defaults.
     */
    protected function getDefaultName(): string
    {
        return static::DEFAULT_NAME;
    }

    /**
     * Initialize agent-specific configuration.
     * Subclasses can override this to handle additional options.
     *
     * @param array<string, mixed> $options Configuration options
     */
    protected function initialize(array $options): void
    {
        // Default implementation does nothing
        // Subclasses can override to handle specific initialization
    }

    /**
     * Log the start of agent execution.
     *
     * @param string $task The task being executed
     * @param array<string, mixed> $context Additional context
     */
    protected function logStart(string $task, array $context = []): void
    {
        $this->logger->info("Starting {$this->name}", array_merge([
            'task' => substr($task, 0, 100),
        ], $context));
    }

    /**
     * Log successful completion of agent execution.
     *
     * @param array<string, mixed> $context Additional context
     */
    protected function logSuccess(array $context = []): void
    {
        $this->logger->info("{$this->name} completed successfully", $context);
    }

    /**
     * Log agent execution failure.
     *
     * @param string $message Error message
     * @param array<string, mixed> $context Additional context
     */
    protected function logError(string $message, array $context = []): void
    {
        $this->logger->error("{$this->name} failed: {$message}", $context);
    }

    /**
     * Log debug information.
     *
     * @param string $message Debug message
     * @param array<string, mixed> $context Additional context
     */
    protected function logDebug(string $message, array $context = []): void
    {
        $this->logger->debug("[{$this->name}] {$message}", $context);
    }
}
