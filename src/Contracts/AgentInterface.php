<?php

declare(strict_types=1);

namespace ClaudeAgents\Contracts;

use ClaudeAgents\AgentResult;

/**
 * Interface for all agent implementations.
 */
interface AgentInterface
{
    /**
     * Run the agent with the given task.
     *
     * @param string $task The task or prompt for the agent
     * @return AgentResult The result of the agent execution
     */
    public function run(string $task): AgentResult;

    /**
     * Get the agent's name/identifier.
     */
    public function getName(): string;
}
