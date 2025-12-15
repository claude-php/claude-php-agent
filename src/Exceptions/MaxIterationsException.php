<?php

declare(strict_types=1);

namespace ClaudeAgents\Exceptions;

/**
 * Thrown when an agent reaches its maximum iteration limit.
 */
class MaxIterationsException extends AgentException
{
    public function __construct(
        int $maxIterations,
        int $currentIteration,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            "Agent reached maximum iterations ({$maxIterations})",
            0,
            $previous,
            [
                'max_iterations' => $maxIterations,
                'current_iteration' => $currentIteration,
            ],
        );
    }
}
