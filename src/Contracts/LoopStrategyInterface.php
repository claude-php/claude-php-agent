<?php

declare(strict_types=1);

namespace ClaudeAgents\Contracts;

use ClaudeAgents\AgentContext;

/**
 * Interface for agent loop strategies.
 *
 * Different strategies implement different agentic patterns:
 * - ReAct: Reason-Act-Observe loop
 * - PlanExecute: Plan then execute steps
 * - Reflection: Generate-Reflect-Refine loop
 */
interface LoopStrategyInterface
{
    /**
     * Execute the loop strategy.
     *
     * @param AgentContext $context The agent execution context
     * @return AgentContext The updated context after execution
     */
    public function execute(AgentContext $context): AgentContext;

    /**
     * Get the strategy name.
     */
    public function getName(): string;
}
