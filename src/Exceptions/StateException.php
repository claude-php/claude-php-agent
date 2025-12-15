<?php

declare(strict_types=1);

namespace ClaudeAgents\Exceptions;

/**
 * Thrown when state management operations fail.
 */
class StateException extends AgentException
{
    public function __construct(
        string $message,
        string $operation = '',
        string $stateFile = '',
        ?\Throwable $previous = null,
    ) {
        $context = [];
        if ($operation !== '') {
            $context['operation'] = $operation;
        }
        if ($stateFile !== '') {
            $context['state_file'] = $stateFile;
        }

        parent::__construct(
            $message,
            0,
            $previous,
            $context,
        );
    }
}
