<?php

declare(strict_types=1);

namespace ClaudeAgents\Exceptions;

/**
 * Thrown when configuration is invalid or misconfigured.
 */
class ConfigurationException extends AgentException
{
    public function __construct(
        string $message,
        string $parameter = '',
        mixed $value = null,
        ?\Throwable $previous = null,
    ) {
        $context = [];
        if ($parameter !== '') {
            $context['parameter'] = $parameter;
        }
        if ($value !== null) {
            $context['value'] = $value;
        }

        parent::__construct(
            $message,
            0,
            $previous,
            $context,
        );
    }
}
