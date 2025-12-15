<?php

declare(strict_types=1);

namespace ClaudeAgents\Contracts;

use ClaudeAgents\AgentResult;

/**
 * Interface for schedulable tasks.
 */
interface SchedulableInterface
{
    /**
     * Execute the scheduled task.
     */
    public function execute(): AgentResult;

    /**
     * Get the task identifier.
     */
    public function getTaskId(): string;
}
