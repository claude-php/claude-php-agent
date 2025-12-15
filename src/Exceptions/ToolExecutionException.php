<?php

declare(strict_types=1);

namespace ClaudeAgents\Exceptions;

/**
 * Thrown when a tool execution fails.
 */
class ToolExecutionException extends AgentException
{
    public function __construct(
        string $toolName,
        string $reason,
        ?\Throwable $previous = null,
        array $input = [],
    ) {
        parent::__construct(
            "Tool '{$toolName}' execution failed: {$reason}",
            0,
            $previous,
            [
                'tool' => $toolName,
                'reason' => $reason,
                'input' => $input,
            ],
        );
    }
}
