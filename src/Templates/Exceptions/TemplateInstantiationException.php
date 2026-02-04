<?php

declare(strict_types=1);

namespace ClaudeAgents\Templates\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Exception thrown when template instantiation fails.
 */
class TemplateInstantiationException extends RuntimeException
{
    public static function agentTypeNotFound(string $agentType): self
    {
        return new self("Agent type '{$agentType}' not found or not instantiable.");
    }

    public static function invalidConfiguration(string $reason, ?Throwable $previous = null): self
    {
        return new self("Invalid template configuration: {$reason}", 0, $previous);
    }

    public static function missingDependency(string $dependency): self
    {
        return new self("Missing required dependency: {$dependency}");
    }

    public static function fromPrevious(string $message, Throwable $previous): self
    {
        return new self($message, 0, $previous);
    }
}
