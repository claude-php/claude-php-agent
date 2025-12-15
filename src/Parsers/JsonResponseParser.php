<?php

declare(strict_types=1);

namespace ClaudeAgents\Parsers;

use ClaudeAgents\Exceptions\ParseException;

/**
 * Parser for JSON responses.
 */
class JsonResponseParser implements ResponseParserInterface
{
    /**
     * @throws ParseException
     * @return array<mixed>|object
     */
    public function parse(string $text)
    {
        $decoded = json_decode($text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ParseException('Invalid JSON: ' . json_last_error_msg());
        }

        return $decoded;
    }

    public function canParse(string $text): bool
    {
        $trimmed = trim($text);

        return (str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}'))
            || (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']'));
    }
}
