<?php

declare(strict_types=1);

namespace ClaudeAgents\Exceptions;

/**
 * Thrown when memory operations fail.
 */
class MemoryException extends AgentException
{
    public function __construct(
        string $message,
        string $operation = '',
        string $memoryType = '',
        ?\Throwable $previous = null,
    ) {
        $context = [];
        if ($operation !== '') {
            $context['operation'] = $operation;
        }
        if ($memoryType !== '') {
            $context['memory_type'] = $memoryType;
        }

        parent::__construct(
            $message,
            0,
            $previous,
            $context,
        );
    }
}
