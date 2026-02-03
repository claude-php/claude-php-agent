<?php

declare(strict_types=1);

namespace ClaudeAgents\Contracts;

use ClaudeAgents\AgentResult;
use ClaudeAgents\Events\FlowEventManager;
use Generator;

/**
 * Interface for agents that support streaming execution.
 *
 * Extends the base AgentInterface to add streaming capabilities
 * for real-time token-by-token responses and progress tracking.
 */
interface StreamableAgentInterface extends AgentInterface
{
    /**
     * Run the agent with streaming support.
     *
     * Returns a Generator that yields tokens as they're received
     * from the LLM, allowing real-time display of responses.
     *
     * @param string $task The task or prompt for the agent
     * @return Generator<int, string> Stream of response tokens
     */
    public function runStreaming(string $task): Generator;

    /**
     * Set the flow event manager for emission during execution.
     *
     * @param FlowEventManager $eventManager Event manager
     * @return self
     */
    public function setFlowEventManager(FlowEventManager $eventManager): self;

    /**
     * Check if the agent supports streaming.
     */
    public function supportsStreaming(): bool;

    /**
     * Get the current execution progress if available.
     *
     * @return array<string, mixed>|null Progress data or null if not tracking
     */
    public function getProgress(): ?array;
}
