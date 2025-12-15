<?php

declare(strict_types=1);

namespace ClaudeAgents\Exceptions;

/**
 * Thrown when context window or context management operations fail.
 */
class ContextException extends AgentException
{
    public function __construct(
        string $message,
        int $currentTokens = 0,
        int $maxTokens = 0,
        string $operation = '',
        ?\Throwable $previous = null,
    ) {
        $context = [];
        if ($currentTokens > 0) {
            $context['current_tokens'] = $currentTokens;
        }
        if ($maxTokens > 0) {
            $context['max_tokens'] = $maxTokens;
        }
        if ($operation !== '') {
            $context['operation'] = $operation;
        }

        parent::__construct(
            $message,
            0,
            $previous,
            $context,
        );
    }
}
