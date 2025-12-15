<?php

declare(strict_types=1);

namespace ClaudeAgents\Exceptions;

/**
 * Thrown when validation fails.
 */
class ValidationException extends AgentException
{
    public function __construct(
        string $message,
        string $field = '',
        mixed $value = null,
        array $violations = [],
        ?\Throwable $previous = null,
    ) {
        $context = [];
        if ($field !== '') {
            $context['field'] = $field;
        }
        if ($value !== null) {
            $context['value'] = $value;
        }
        if (! empty($violations)) {
            $context['violations'] = $violations;
        }

        parent::__construct(
            $message,
            0,
            $previous,
            $context,
        );
    }
}
