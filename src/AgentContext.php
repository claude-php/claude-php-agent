<?php

declare(strict_types=1);

namespace ClaudeAgents;

use ClaudeAgents\Context\ContextManager;
use ClaudeAgents\Contracts\MemoryInterface;
use ClaudeAgents\Contracts\ToolInterface;
use ClaudePhp\ClaudePhp;

/**
 * Holds the execution context for an agent run.
 *
 * Contains all state needed during agent execution including
 * messages, tools, configuration, and runtime state.
 */
class AgentContext
{
    /**
     * @var array<array<string, mixed>> Conversation messages
     */
    private array $messages = [];

    /**
     * @var int Current iteration count
     */
    private int $iteration = 0;

    /**
     * @var bool Whether the agent has completed
     */
    private bool $completed = false;

    /**
     * @var string|null The final answer if completed
     */
    private ?string $answer = null;

    /**
     * @var string|null Error message if failed
     */
    private ?string $error = null;

    /**
     * @var array<array<string, mixed>> Tool calls made during execution
     */
    private array $toolCalls = [];

    /**
     * @var array{input: int, output: int} Token usage tracking
     */
    private array $tokenUsage = ['input' => 0, 'output' => 0];

    /**
     * @var array<string, mixed> Arbitrary metadata collected during execution
     */
    private array $metadata = [];

    /**
     * @var ContextManager|null Optional context manager
     */
    private ?ContextManager $contextManager = null;

    /**
     * @var float Start time of execution
     */
    private float $startTime;

    /**
     * @var float|null End time of execution
     */
    private ?float $endTime = null;

    /**
     * @var array<string, array{messages: array, iteration: int, timestamp: float}> Checkpoints
     */
    private array $checkpoints = [];

    /**
     * @param ClaudePhp $client The Claude API client
     * @param string $task The task to execute
     * @param array<ToolInterface> $tools Available tools
     * @param Config\AgentConfig $config Agent configuration
     * @param MemoryInterface|null $memory Optional memory store
     * @param ContextManager|null $contextManager Optional context manager
     */
    public function __construct(
        private readonly ClaudePhp $client,
        private readonly string $task,
        /** @var array<ToolInterface> */
        private array $tools,
        private readonly Config\AgentConfig $config,
        private readonly ?MemoryInterface $memory = null,
        ?ContextManager $contextManager = null,
    ) {
        $this->contextManager = $contextManager;
        $this->startTime = microtime(true);
        // Initialize with the user task
        $this->messages[] = [
            'role' => 'user',
            'content' => $task,
        ];
    }

    /**
     * Get the Claude client.
     */
    public function getClient(): ClaudePhp
    {
        return $this->client;
    }

    /**
     * Get the original task.
     */
    public function getTask(): string
    {
        return $this->task;
    }

    /**
     * Get available tools.
     *
     * @return array<ToolInterface>
     */
    public function getTools(): array
    {
        return $this->tools;
    }

    /**
     * Get tool definitions for API calls.
     *
     * @return array<array<string, mixed>>
     */
    public function getToolDefinitions(): array
    {
        return array_map(
            fn(ToolInterface $tool) => $tool->toDefinition(),
            $this->tools
        );
    }

    /**
     * Get a tool by name.
     */
    public function getTool(string $name): ?ToolInterface
    {
        foreach ($this->tools as $tool) {
            if ($tool->getName() === $name) {
                return $tool;
            }
        }

        return null;
    }

    /**
     * Add a tool to the available tools at runtime.
     *
     * The new tool will be included in the next API call's tool definitions.
     */
    public function addTool(ToolInterface $tool): void
    {
        // Avoid duplicates
        if ($this->getTool($tool->getName()) !== null) {
            return;
        }

        $this->tools[] = $tool;
    }

    /**
     * Remove a tool by name at runtime.
     *
     * The tool will no longer appear in subsequent API calls.
     */
    public function removeTool(string $name): void
    {
        $this->tools = array_values(array_filter(
            $this->tools,
            fn(ToolInterface $tool) => $tool->getName() !== $name
        ));
    }

    /**
     * Get the agent configuration.
     */
    public function getConfig(): Config\AgentConfig
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
     * Get conversation messages.
     *
     * @return array<array<string, mixed>>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Get conversation messages with optional automatic compaction.
     *
     * @return array<array<string, mixed>>
     */
    public function getMessagesWithCompaction(): array
    {
        if ($this->contextManager === null) {
            return $this->messages;
        }

        return $this->contextManager->compactMessages(
            $this->messages,
            $this->getToolDefinitions()
        );
    }

    /**
     * Add a message to the conversation.
     *
     * @param array<string, mixed> $message
     */
    public function addMessage(array $message): void
    {
        $this->messages[] = $message;

        // Auto-compact if context manager is configured and threshold exceeded.
        // Skip compaction when the last message is an assistant message with
        // tool_use blocks but no following tool_result. Compacting at this point
        // would separate the tool_use from its tool_result (which hasn't been
        // added yet), corrupting the message structure required by the API.
        if ($this->contextManager !== null && ! $this->hasDanglingToolUse()) {
            $usage = $this->contextManager->getUsagePercentage(
                $this->messages,
                $this->getToolDefinitions()
            );

            if ($usage >= $this->contextManager->getCompactThreshold()) {
                $this->messages = $this->contextManager->compactMessages(
                    $this->messages,
                    $this->getToolDefinitions()
                );
            }
        }
    }

    /**
     * Check if the last message is an assistant message with tool_use blocks
     * that doesn't have a following tool_result message.
     *
     * This indicates we're between adding the assistant response and the
     * tool results, and compaction must be deferred to avoid orphaning
     * the tool_use blocks.
     */
    private function hasDanglingToolUse(): bool
    {
        if (empty($this->messages)) {
            return false;
        }

        $lastMessage = $this->messages[count($this->messages) - 1];

        if (($lastMessage['role'] ?? '') !== 'assistant') {
            return false;
        }

        if (! is_array($lastMessage['content'] ?? null)) {
            return false;
        }

        foreach ($lastMessage['content'] as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'tool_use') {
                return true;
            }
        }

        return false;
    }

    /**
     * Get current iteration count.
     */
    public function getIteration(): int
    {
        return $this->iteration;
    }

    /**
     * Increment the iteration counter.
     */
    public function incrementIteration(): void
    {
        $this->iteration++;
    }

    /**
     * Check if max iterations reached.
     */
    public function hasReachedMaxIterations(): bool
    {
        return $this->iteration >= $this->config->getMaxIterations();
    }

    /**
     * Check if agent has completed.
     */
    public function isCompleted(): bool
    {
        return $this->completed;
    }

    /**
     * Mark the agent as completed with an answer.
     */
    public function complete(string $answer): void
    {
        $this->completed = true;
        $this->answer = $answer;
        $this->endTime = microtime(true);
    }

    /**
     * Mark the agent as failed with an error.
     */
    public function fail(string $error): void
    {
        $this->completed = true;
        $this->error = $error;
        $this->endTime = microtime(true);
    }

    /**
     * Get the final answer.
     */
    public function getAnswer(): ?string
    {
        return $this->answer;
    }

    /**
     * Get the error message.
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Check if execution failed.
     */
    public function hasFailed(): bool
    {
        return $this->error !== null;
    }

    /**
     * Record a tool call.
     *
     * @param string $toolName
     * @param array<string, mixed> $input
     * @param string $result
     * @param bool $isError
     */
    public function recordToolCall(
        string $toolName,
        array $input,
        string $result,
        bool $isError = false,
    ): void {
        $this->toolCalls[] = [
            'tool' => $toolName,
            'input' => $input,
            'result' => $result,
            'is_error' => $isError,
            'iteration' => $this->iteration,
            'timestamp' => time(),
        ];
    }

    /**
     * Get all tool calls.
     *
     * @return array<array<string, mixed>>
     */
    public function getToolCalls(): array
    {
        return $this->toolCalls;
    }

    /**
     * Add token usage.
     */
    public function addTokenUsage(int $input, int $output): void
    {
        $this->tokenUsage['input'] += $input;
        $this->tokenUsage['output'] += $output;
    }

    /**
     * Get token usage.
     *
     * @return array{input: int, output: int, total: int}
     */
    public function getTokenUsage(): array
    {
        return [
            'input' => $this->tokenUsage['input'],
            'output' => $this->tokenUsage['output'],
            'total' => $this->tokenUsage['input'] + $this->tokenUsage['output'],
        ];
    }

    /**
     * Add arbitrary metadata to the context (for loop strategies, evaluators, etc).
     */
    public function addMetadata(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
    }

    /**
     * Get metadata value or default.
     */
    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Get all metadata.
     *
     * @return array<string, mixed>
     */
    public function getAllMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get the context manager.
     */
    public function getContextManager(): ?ContextManager
    {
        return $this->contextManager;
    }

    /**
     * Check if messages fit in context window.
     */
    public function fitsInContext(): bool
    {
        if ($this->contextManager === null) {
            return true;
        }

        return $this->contextManager->fitsInContext(
            $this->messages,
            $this->getToolDefinitions()
        );
    }

    /**
     * Get context usage percentage (0.0 to 1.0+).
     */
    public function getContextUsage(): float
    {
        if ($this->contextManager === null) {
            return 0.0;
        }

        return $this->contextManager->getUsagePercentage(
            $this->messages,
            $this->getToolDefinitions()
        );
    }

    /**
     * Get execution time in seconds.
     */
    public function getExecutionTime(): float
    {
        $endTime = $this->endTime ?? microtime(true);

        return $endTime - $this->startTime;
    }

    /**
     * Get average time per iteration.
     */
    public function getTimePerIteration(): float
    {
        if ($this->iteration === 0) {
            return 0.0;
        }

        return $this->getExecutionTime() / $this->iteration;
    }

    /**
     * Get start time.
     */
    public function getStartTime(): float
    {
        return $this->startTime;
    }

    /**
     * Get end time.
     */
    public function getEndTime(): ?float
    {
        return $this->endTime;
    }

    /**
     * Set messages directly (use with caution).
     *
     * @param array<array<string, mixed>> $messages
     */
    public function setMessages(array $messages): void
    {
        $this->messages = $messages;
    }

    /**
     * Clear all messages except the initial task.
     */
    public function clearMessages(): void
    {
        $this->messages = [
            ['role' => 'user', 'content' => $this->task],
        ];
    }

    /**
     * Remove a message at the given index.
     */
    public function removeMessage(int $index): void
    {
        if (isset($this->messages[$index])) {
            array_splice($this->messages, $index, 1);
        }
    }

    /**
     * Replace the last message.
     *
     * @param array<string, mixed> $message
     */
    public function replaceLastMessage(array $message): void
    {
        if (! empty($this->messages)) {
            $this->messages[count($this->messages) - 1] = $message;
        }
    }

    /**
     * Create a checkpoint of the current state.
     *
     * @return string Checkpoint ID
     */
    public function createCheckpoint(?string $id = null): string
    {
        $checkpointId = $id ?? uniqid('checkpoint_', true);

        $this->checkpoints[$checkpointId] = [
            'messages' => $this->messages,
            'iteration' => $this->iteration,
            'timestamp' => microtime(true),
            'token_usage' => $this->tokenUsage,
            'tool_calls' => $this->toolCalls,
            'metadata' => $this->metadata,
        ];

        return $checkpointId;
    }

    /**
     * Restore state from a checkpoint.
     */
    public function restoreCheckpoint(string $id): void
    {
        if (! isset($this->checkpoints[$id])) {
            throw new \InvalidArgumentException("Checkpoint '{$id}' not found");
        }

        $checkpoint = $this->checkpoints[$id];
        $this->messages = $checkpoint['messages'];
        $this->iteration = $checkpoint['iteration'];
        $this->tokenUsage = $checkpoint['token_usage'];
        $this->toolCalls = $checkpoint['tool_calls'];
        $this->metadata = $checkpoint['metadata'] ?? [];
    }

    /**
     * Get all checkpoints.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getCheckpoints(): array
    {
        return $this->checkpoints;
    }

    /**
     * Check if a checkpoint exists.
     */
    public function hasCheckpoint(string $id): bool
    {
        return isset($this->checkpoints[$id]);
    }

    /**
     * Delete a checkpoint.
     */
    public function deleteCheckpoint(string $id): void
    {
        unset($this->checkpoints[$id]);
    }

    /**
     * Clone the context for parallel execution.
     */
    public function fork(): self
    {
        $forked = new self(
            client: $this->client,
            task: $this->task,
            tools: $this->tools,
            config: $this->config,
            memory: $this->memory,
            contextManager: $this->contextManager,
        );

        // Copy current state
        $forked->messages = $this->messages;
        $forked->iteration = $this->iteration;
        $forked->tokenUsage = $this->tokenUsage;
        $forked->toolCalls = $this->toolCalls;
        $forked->metadata = $this->metadata;
        $forked->startTime = $this->startTime;

        return $forked;
    }

    /**
     * Export state to array for serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'task' => $this->task,
            'messages' => $this->messages,
            'iteration' => $this->iteration,
            'completed' => $this->completed,
            'answer' => $this->answer,
            'error' => $this->error,
            'tool_calls' => $this->toolCalls,
            'token_usage' => $this->tokenUsage,
            'metadata' => $this->metadata,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'execution_time' => $this->getExecutionTime(),
        ];
    }

    /**
     * String representation of the context.
     */
    public function __toString(): string
    {
        $status = $this->completed ? 'Completed' : 'In Progress';
        $msgCount = count($this->messages);
        $time = number_format($this->getExecutionTime(), 2);

        return "AgentContext [{$status}]: {$this->iteration} iterations, {$msgCount} messages, {$time}s";
    }

    /**
     * Build the result from the context.
     */
    public function toResult(): AgentResult
    {
        $metadata = [
            'token_usage' => $this->getTokenUsage(),
            'tool_calls' => $this->toolCalls,
            'execution_time' => $this->getExecutionTime(),
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
        ];
        if (! empty($this->metadata)) {
            // Expose loop-specific metadata (e.g. reflection scores, plan steps)
            // at the top level of AgentResult::getMetadata() for compatibility.
            $metadata = array_merge($metadata, $this->metadata);
        }

        if ($this->hasFailed()) {
            return AgentResult::failure(
                error: $this->error ?? 'Unknown error',
                messages: $this->messages,
                iterations: $this->iteration,
                metadata: $metadata,
            );
        }

        return AgentResult::success(
            answer: $this->answer ?? '',
            messages: $this->messages,
            iterations: $this->iteration,
            metadata: $metadata,
        );
    }
}
