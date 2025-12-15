<?php

declare(strict_types=1);

namespace ClaudeAgents\Exceptions;

/**
 * Thrown when parsing fails.
 */
class ParseException extends AgentException
{
    public function __construct(
        string $message,
        string $input = '',
        string $parserType = '',
        ?\Throwable $previous = null,
    ) {
        $context = [];
        if ($input !== '') {
            // Truncate input for context to avoid huge error messages
            $context['input'] = strlen($input) > 200
                ? substr($input, 0, 200) . '...'
                : $input;
        }
        if ($parserType !== '') {
            $context['parser_type'] = $parserType;
        }

        parent::__construct(
            $message,
            0,
            $previous,
            $context,
        );
    }
}
