<?php

declare(strict_types=1);

namespace ClaudeAgents;

use ClaudeAgents\Config\AgentConfig;
use ClaudeAgents\Config\RetryConfig;
use ClaudeAgents\Context\ContextManager;
use ClaudeAgents\Contracts\AgentInterface;
use ClaudeAgents\Contracts\CallbackSupportingLoopInterface;
use ClaudeAgents\Contracts\LoopStrategyInterface;
use ClaudeAgents\Contracts\MemoryInterface;
use ClaudeAgents\Contracts\ToolInterface;
use ClaudeAgents\Loops\ReactLoop;
use ClaudeAgents\Memory\Memory;
use ClaudeAgents\Support\RetryHandler;
use ClaudeAgents\Tools\Tool;
use ClaudeAgents\Tools\ToolRegistry;
use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Main Agent class with fluent builder API.
 *
 * Provides a simple way to create and configure agents with tools,
 * memory, and various execution strategies.
 *
 * @example
 * ```php
 * $agent = Agent::create($client)
 *     ->withTool($calculator)
 *     ->withSystemPrompt('You are a helpful assistant.')
 *     ->maxIterations(10)
 *     ->run('What is 25 * 17?');
 * ```
 */
class Agent implements AgentInterface
{
    private ClaudePhp $client;
    private ToolRegistry $tools;
    private AgentConfig $config;
    private ?MemoryInterface $memory = null;
    private ?ContextManager $contextManager = null;
    private LoopStrategyInterface $loopStrategy;
    private LoggerInterface $logger;
    private string $name = 'agent';
    private ?RetryConfig $retryConfig = null;
    private ?AgentContext $pausedContext = null;

    /**
     * @var callable|null
     */
    private $onIteration = null;

    /**
     * @var callable|null
     */
    private $onToolExecution = null;

    /**
     * @var callable|null
     */
    private $onError = null;

    public function __construct(
        ClaudePhp $client,
        ?AgentConfig $config = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->client = $client;
        $this->config = $config ?? new AgentConfig();
        $this->logger = $logger ?? new NullLogger();
        $this->tools = new ToolRegistry();
        $this->loopStrategy = new ReactLoop($this->logger);
    }

    /**
     * Create a new agent instance.
     */
    public static function create(ClaudePhp $client): self
    {
        return new self($client);
    }

    /**
     * Set the agent name.
     */
    public function withName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Add a tool to the agent.
     */
    public function withTool(ToolInterface $tool): self
    {
        $this->tools->register($tool);

        return $this;
    }

    /**
     * Add multiple tools to the agent.
     *
     * @param array<ToolInterface> $tools
     */
    public function withTools(array $tools): self
    {
        $this->tools->registerMany($tools);

        return $this;
    }

    /**
     * Set the system prompt.
     */
    public function withSystemPrompt(string $prompt): self
    {
        $this->config = $this->config->with(['system_prompt' => $prompt]);

        return $this;
    }

    /**
     * Set the model to use.
     */
    public function withModel(string $model): self
    {
        $this->config = $this->config->with(['model' => $model]);

        return $this;
    }

    /**
     * Set the configuration.
     */
    public function withConfig(AgentConfig $config): self
    {
        $this->config = $config;

        return $this;
    }

    /**
     * Set configuration from array.
     *
     * @param array<string, mixed> $config
     */
    public function configure(array $config): self
    {
        $this->config = AgentConfig::fromArray($config);

        return $this;
    }

    /**
     * Set the memory store.
     */
    public function withMemory(?MemoryInterface $memory = null): self
    {
        $this->memory = $memory ?? new Memory();

        return $this;
    }

    /**
     * Set the loop strategy.
     */
    public function withLoopStrategy(LoopStrategyInterface $strategy): self
    {
        $this->loopStrategy = $strategy;

        return $this;
    }

    /**
     * Set the logger.
     */
    public function withLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;
        // Update loop strategy logger if it's a ReactLoop
        if ($this->loopStrategy instanceof ReactLoop) {
            $this->loopStrategy = new ReactLoop($logger);
        }

        return $this;
    }

    /**
     * Set maximum iterations.
     */
    public function maxIterations(int $max): self
    {
        $this->config = $this->config->with(['max_iterations' => $max]);

        return $this;
    }

    /**
     * Set maximum tokens per response.
     */
    public function maxTokens(int $max): self
    {
        $this->config = $this->config->with(['max_tokens' => $max]);

        return $this;
    }

    /**
     * Set temperature.
     */
    public function temperature(float $temp): self
    {
        $this->config = $this->config->with(['temperature' => $temp]);

        return $this;
    }

    /**
     * Enable extended thinking.
     *
     * @param int $budgetTokens Token budget for thinking
     */
    public function withThinking(int $budgetTokens = 10000): self
    {
        $this->config = $this->config->with([
            'thinking' => [
                'type' => 'enabled',
                'budget_tokens' => $budgetTokens,
            ],
        ]);

        return $this;
    }

    /**
     * Set iteration callback.
     *
     * @param callable $callback fn(int $iteration, mixed $response, AgentContext $context)
     */
    public function onIteration(callable $callback): self
    {
        $this->onIteration = $callback;

        return $this;
    }

    /**
     * Set tool execution callback.
     *
     * @param callable $callback fn(string $tool, array $input, ToolResult $result)
     */
    public function onToolExecution(callable $callback): self
    {
        $this->onToolExecution = $callback;

        return $this;
    }

    /**
     * Set error callback.
     *
     * @param callable $callback fn(Throwable $error, int $attempt)
     */
    public function onError(callable $callback): self
    {
        $this->onError = $callback;

        return $this;
    }

    /**
     * Enable context management with automatic compaction.
     *
     * @param int $maxContextTokens Maximum context window size
     * @param array<string, mixed> $options Additional options
     */
    public function withContextManagement(int $maxContextTokens = 100000, array $options = []): self
    {
        $this->contextManager = new ContextManager(
            $maxContextTokens,
            array_merge(['logger' => $this->logger], $options)
        );

        return $this;
    }

    /**
     * Set a custom context manager.
     */
    public function withContextManager(ContextManager $contextManager): self
    {
        $this->contextManager = $contextManager;

        return $this;
    }

    /**
     * Enable retry logic with exponential backoff.
     *
     * @param int $maxAttempts Maximum retry attempts
     * @param int $delayMs Initial delay in milliseconds
     * @param int $maxDelayMs Maximum delay in milliseconds
     * @param float $multiplier Backoff multiplier
     */
    public function withRetry(
        int $maxAttempts = 3,
        int $delayMs = 1000,
        int $maxDelayMs = 30000,
        float $multiplier = 2.0,
    ): self {
        $this->retryConfig = new RetryConfig(
            maxAttempts: $maxAttempts,
            delayMs: $delayMs,
            maxDelayMs: $maxDelayMs,
            multiplier: $multiplier,
        );

        return $this;
    }

    /**
     * Set retry configuration.
     */
    public function withRetryConfig(RetryConfig $config): self
    {
        $this->retryConfig = $config;

        return $this;
    }

    /**
     * Pause the current execution.
     * Can be resumed later with resume().
     */
    public function pause(): void
    {
        // This will be set by the execution context during run
        // The actual pause logic is handled via callbacks
    }

    /**
     * Check if agent has a paused context.
     */
    public function isPaused(): bool
    {
        return $this->pausedContext !== null;
    }

    /**
     * Get the paused context.
     */
    public function getPausedContext(): ?AgentContext
    {
        return $this->pausedContext;
    }

    /**
     * Resume from a paused context.
     */
    public function resume(?AgentContext $context = null): AgentResult
    {
        $contextToResume = $context ?? $this->pausedContext;

        if ($contextToResume === null) {
            throw new \RuntimeException('No paused context to resume from');
        }

        $this->logger->info("Resuming agent '{$this->name}' from paused state", [
            'iteration' => $contextToResume->getIteration(),
        ]);

        // Clear paused state
        $this->pausedContext = null;

        // Configure loop strategy callbacks
        $this->configureLoopCallbacks();

        try {
            // Continue execution with the existing context
            $contextToResume = $this->loopStrategy->execute($contextToResume);
        } catch (\Throwable $e) {
            $this->logger->error("Agent resume failed: {$e->getMessage()}");

            if ($this->onError !== null) {
                ($this->onError)($e, 1);
            }

            return AgentResult::failure(
                error: $e->getMessage(),
                messages: $contextToResume->getMessages(),
                iterations: $contextToResume->getIteration(),
            );
        }

        return $contextToResume->toResult();
    }

    /**
     * Save the current agent state.
     *
     * @return array<string, mixed>
     */
    public function saveState(?AgentContext $context = null): array
    {
        $ctx = $context ?? $this->pausedContext;

        if ($ctx === null) {
            throw new \RuntimeException('No context to save');
        }

        return [
            'name' => $this->name,
            'context' => $ctx->toArray(),
            'config' => [
                'model' => $this->config->getModel(),
                'max_iterations' => $this->config->getMaxIterations(),
                'max_tokens' => $this->config->getMaxTokens(),
                'temperature' => $this->config->getTemperature(),
            ],
            'timestamp' => time(),
        ];
    }

    /**
     * Restore agent state from saved data.
     *
     * @param array<string, mixed> $state
     */
    public function restoreState(array $state): void
    {
        // This is a simplified restore - in practice you'd need to
        // reconstruct the full context with client, tools, etc.
        // For now, we just store the state data
        $this->logger->info('State restore requested', [
            'name' => $state['name'] ?? 'unknown',
        ]);

        // Note: Full state restoration would require reconstructing AgentContext
        // which needs the client and tools. This is a starting point.
    }

    /**
     * Run the agent with the given task.
     */
    public function run(string $task): AgentResult
    {
        $this->logger->info("Starting agent '{$this->name}' with task", [
            'task' => substr($task, 0, 100),
            'tools' => $this->tools->names(),
        ]);

        // Create execution context
        $context = new AgentContext(
            client: $this->client,
            task: $task,
            tools: $this->tools->all(),
            config: $this->config,
            memory: $this->memory,
            contextManager: $this->contextManager,
        );

        // Configure loop strategy callbacks
        $this->configureLoopCallbacks();

        try {
            // Execute with or without retry
            if ($this->retryConfig !== null) {
                $retryHandler = new RetryHandler($this->retryConfig, $this->logger);
                $context = $retryHandler->execute(
                    fn () => $this->loopStrategy->execute($context)
                );
            } else {
                // Execute the loop strategy
                $context = $this->loopStrategy->execute($context);
            }
        } catch (\Throwable $e) {
            $this->logger->error("Agent execution failed: {$e->getMessage()}");

            if ($this->onError !== null) {
                ($this->onError)($e, 1);
            }

            return AgentResult::failure(
                error: $e->getMessage(),
                messages: $context->getMessages(),
                iterations: $context->getIteration(),
            );
        }

        $result = $context->toResult();

        $this->logger->info('Agent completed', [
            'success' => $result->isSuccess(),
            'iterations' => $result->getIterations(),
            'tokens' => $result->getTokenUsage(),
        ]);

        return $result;
    }

    /**
     * Get the agent name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the tool registry.
     */
    public function getTools(): ToolRegistry
    {
        return $this->tools;
    }

    /**
     * Get the current configuration.
     */
    public function getConfig(): AgentConfig
    {
        return $this->config;
    }

    /**
     * Get the memory store.
     */
    public function getMemory(): ?MemoryInterface
    {
        return $this->memory;
    }

    /**
     * Configure loop strategy callbacks (internal helper).
     */
    private function configureLoopCallbacks(): void
    {
        if ($this->loopStrategy instanceof CallbackSupportingLoopInterface) {
            if ($this->onIteration !== null) {
                $this->loopStrategy->onIteration($this->onIteration);
            }
            if ($this->onToolExecution !== null) {
                $this->loopStrategy->onToolExecution($this->onToolExecution);
            }
        }
    }
}
