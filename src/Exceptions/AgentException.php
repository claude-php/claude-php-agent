<?php

declare(strict_types=1);

namespace ClaudeAgents\Exceptions;

/**
 * Base exception for agent-related errors.
 */
class AgentException extends \RuntimeException
{
    /**
     * @var array<string, mixed>
     */
    protected array $context = [];

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        array $context = [],
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * Get additional context about the exception.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
