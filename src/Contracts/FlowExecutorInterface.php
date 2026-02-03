<?php

declare(strict_types=1);

namespace ClaudeAgents\Contracts;

use ClaudeAgents\AgentResult;
use Generator;

/**
 * Interface for flow executors that support streaming.
 *
 * Flow executors manage the execution lifecycle of agents,
 * providing streaming capabilities for real-time feedback.
 */
interface FlowExecutorInterface
{
    /**
     * Execute an agent with streaming support.
     *
     * Returns a Generator that yields flow events as they occur:
     * - Token events for real-time text streaming
     * - Progress events for execution tracking
     * - Tool events for tool execution feedback
     * - Error events for failures
     *
     * @param AgentInterface $agent Agent to execute
     * @param string $input Input task or prompt
     * @param array<string, mixed> $options Execution options
     * @return Generator<int, array{type: string, data: mixed}> Stream of flow events
     */
    public function executeWithStreaming(
        AgentInterface $agent,
        string $input,
        array $options = []
    ): Generator;

    /**
     * Execute an agent without streaming (blocking).
     *
     * @param AgentInterface $agent Agent to execute
     * @param string $input Input task or prompt
     * @param array<string, mixed> $options Execution options
     * @return AgentResult Execution result
     */
    public function execute(
        AgentInterface $agent,
        string $input,
        array $options = []
    ): AgentResult;

    /**
     * Check if the executor is currently running.
     */
    public function isRunning(): bool;

    /**
     * Get the executor name/identifier.
     */
    public function getName(): string;
}
