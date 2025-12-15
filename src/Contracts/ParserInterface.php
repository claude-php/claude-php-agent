<?php

declare(strict_types=1);

namespace ClaudeAgents\Contracts;

/**
 * Interface for output parsers that extract structured data from LLM responses.
 *
 * Parsers transform unstructured text output from language models into
 * structured data formats that can be easily consumed by applications.
 */
interface ParserInterface
{
    /**
     * Parse text into structured data.
     *
     * @param string $text The text to parse
     * @throws \RuntimeException If parsing fails
     * @return mixed The parsed data (array, object, scalar, etc.)
     */
    public function parse(string $text): mixed;

    /**
     * Get the format instructions for the LLM.
     *
     * Returns instructions that can be included in prompts to guide
     * the LLM to produce output in the expected format.
     *
     * @return string Instructions for the LLM
     */
    public function getFormatInstructions(): string;

    /**
     * Get the parser type/name.
     *
     * @return string Parser identifier (e.g., 'json', 'list', 'regex')
     */
    public function getType(): string;
}
