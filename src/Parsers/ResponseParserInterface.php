<?php

declare(strict_types=1);

namespace ClaudeAgents\Parsers;

/**
 * Interface for response parsing strategies.
 */
interface ResponseParserInterface
{
    /**
     * Parse the response text.
     *
     * @return mixed Parsed result
     */
    public function parse(string $text);

    /**
     * Check if the text can be parsed by this parser.
     */
    public function canParse(string $text): bool;
}
