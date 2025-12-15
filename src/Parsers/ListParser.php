<?php

declare(strict_types=1);

namespace ClaudeAgents\Parsers;

use ClaudeAgents\Contracts\ParserInterface;

/**
 * Parses lists/arrays from LLM responses.
 *
 * Supports various list formats:
 * - Bullet lists (-, *, •)
 * - Numbered lists (1., 2., 3. or 1) 2) 3))
 * - Plain line-separated items
 *
 * Can extract nested lists and preserve structure.
 */
class ListParser implements ParserInterface
{
    /**
     * @var string Default pattern for parsing
     */
    private string $defaultPattern = '/^[-*]\s+(.+)$/m';

    /**
     * @var bool Whether to preserve empty lines
     */
    private bool $preserveEmpty = false;

    /**
     * Set custom default pattern.
     *
     * @param string $pattern Regex pattern
     * @return self
     */
    public function withPattern(string $pattern): self
    {
        $this->defaultPattern = $pattern;

        return $this;
    }

    /**
     * Preserve empty lines in output.
     *
     * @return self
     */
    public function preserveEmpty(): self
    {
        $this->preserveEmpty = true;

        return $this;
    }

    /**
     * Parse a list from response text.
     *
     * @param string $text The response text
     * @param string|null $pattern Optional pattern override
     * @return array<string> Parsed list items
     */
    public function parse(string $text, ?string $pattern = null): array
    {
        $pattern ??= $this->defaultPattern;

        if (preg_match_all($pattern, $text, $matches)) {
            return array_map('trim', $matches[1]);
        }

        // Fallback: split by lines
        $lines = array_map('trim', explode("\n", $text));

        if (! $this->preserveEmpty) {
            $lines = array_filter($lines, fn ($line) => ! empty($line) && strlen($line) > 2);
        }

        return array_values($lines);
    }

    /**
     * Parse numbered list.
     *
     * @param string $text The response text
     * @return array<string> Parsed items
     */
    public function parseNumbered(string $text): array
    {
        return $this->parse($text, '/^\d+[\.\)]\s+(.+)$/m');
    }

    /**
     * Parse bullet list.
     *
     * @param string $text The response text
     * @return array<string> Parsed items
     */
    public function parseBullets(string $text): array
    {
        return $this->parse($text, '/^[-*•]\s+(.+)$/m');
    }

    /**
     * Get format instructions for the LLM.
     *
     * @return string
     */
    public function getFormatInstructions(): string
    {
        return "Return your response as a bullet-point list. Start each item with a dash (-).\n\n" .
               "Example:\n" .
               "- First item\n" .
               "- Second item\n" .
               '- Third item';
    }

    /**
     * Get parser type.
     *
     * @return string
     */
    public function getType(): string
    {
        return 'list';
    }

    /**
     * Parse comma-separated values.
     *
     * @param string $text The text containing CSV
     * @param string $delimiter Delimiter character
     * @return array<string> Parsed items
     */
    public function parseCsv(string $text, string $delimiter = ','): array
    {
        return array_map('trim', explode($delimiter, $text));
    }

    /**
     * Parse nested lists (up to 2 levels).
     *
     * @param string $text The text with nested lists
     * @return array<string, array<string>> Nested array structure
     */
    public function parseNested(string $text): array
    {
        $result = [];
        $currentParent = null;

        $lines = explode("\n", $text);

        foreach ($lines as $line) {
            $trimmed = ltrim($line);
            $indent = strlen($line) - strlen($trimmed);

            // Top level item
            if (preg_match('/^[-*•]\s+(.+)$/', $trimmed, $matches)) {
                if ($indent === 0) {
                    $currentParent = $matches[1];
                    $result[$currentParent] = [];
                } elseif ($currentParent !== null) {
                    // Nested item
                    $result[$currentParent][] = $matches[1];
                }
            }
        }

        return $result;
    }
}
