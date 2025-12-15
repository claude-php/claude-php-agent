<?php

declare(strict_types=1);

namespace ClaudeAgents\Parsers;

use ClaudeAgents\Contracts\ParserInterface;

/**
 * Regex-based parsing for extracting data from responses.
 *
 * Provides flexible pattern-based extraction with:
 * - Single and multiple match extraction
 * - Key-value pair extraction
 * - Common patterns (emails, URLs, numbers)
 * - Custom capture group selection
 */
class RegexParser implements ParserInterface
{
    /**
     * @var string|null Default pattern to use
     */
    private ?string $defaultPattern = null;

    /**
     * @var int Default capture group
     */
    private int $defaultCaptureGroup = 1;

    /**
     * Set default pattern for parse() method.
     *
     * @param string $pattern Regex pattern
     * @param int $captureGroup Capture group to extract
     * @return self
     */
    public function withPattern(string $pattern, int $captureGroup = 1): self
    {
        $this->defaultPattern = $pattern;
        $this->defaultCaptureGroup = $captureGroup;

        return $this;
    }

    /**
     * Parse text using the configured pattern.
     *
     * @param string $text The text to parse
     * @throws \RuntimeException If no pattern configured or no matches found
     * @return array<string>|string Extracted matches
     */
    public function parse(string $text): array|string
    {
        if ($this->defaultPattern === null) {
            throw new \RuntimeException('No pattern configured. Use withPattern() first.');
        }

        $result = $this->extract($text, $this->defaultPattern, $this->defaultCaptureGroup);

        if (empty($result)) {
            throw new \RuntimeException("No matches found for pattern: {$this->defaultPattern}");
        }

        return count($result) === 1 ? $result[0] : $result;
    }

    /**
     * Extract all matches using a regex pattern.
     *
     * @param string $text The response text
     * @param string $pattern The regex pattern
     * @param int $captureGroup Which capture group to extract (default: 1)
     * @return array<string> Extracted matches
     */
    public function extract(string $text, string $pattern, int $captureGroup = 1): array
    {
        if (preg_match_all($pattern, $text, $matches)) {
            return $matches[$captureGroup] ?? [];
        }

        return [];
    }

    /**
     * Extract a single match.
     *
     * @param string $text The response text
     * @param string $pattern The regex pattern
     * @param int $captureGroup Which capture group to extract
     * @return string|null First match, or null if not found
     */
    public function extractOne(string $text, string $pattern, int $captureGroup = 1): ?string
    {
        if (preg_match($pattern, $text, $matches)) {
            return $matches[$captureGroup] ?? null;
        }

        return null;
    }

    /**
     * Extract key-value pairs.
     *
     * @param string $text The response text
     * @param string $pattern Pattern with {key} and {value} capture groups
     * @return array<string, string> Map of key to value
     */
    public function extractKeyValue(string $text, string $pattern): array
    {
        $pairs = [];

        if (preg_match_all($pattern, $text, $matches)) {
            $keys = $matches['key'] ?? $matches[1] ?? [];
            $values = $matches['value'] ?? $matches[2] ?? [];

            for ($i = 0; $i < count($keys); $i++) {
                $pairs[$keys[$i]] = $values[$i] ?? '';
            }
        }

        return $pairs;
    }

    /**
     * Common pattern: extract number.
     */
    public function extractNumber(string $text): ?float
    {
        $result = $this->extractOne($text, '/(-?\d+\.?\d*)/', 1);

        return $result !== null ? (float) $result : null;
    }

    /**
     * Common pattern: extract email.
     */
    public function extractEmails(string $text): array
    {
        return $this->extract($text, '/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/', 1);
    }

    /**
     * Common pattern: extract URL.
     */
    public function extractUrls(string $text): array
    {
        return $this->extract($text, '/(https?:\/\/[^\s]+)/', 1);
    }

    /**
     * Extract phone numbers.
     *
     * @param string $text The text to search
     * @return array<string> Found phone numbers
     */
    public function extractPhoneNumbers(string $text): array
    {
        // Matches various phone number formats
        return $this->extract($text, '/(\+?[\d\s\-\(\)]{10,})/', 1);
    }

    /**
     * Extract dates (various formats).
     *
     * @param string $text The text to search
     * @return array<string> Found dates
     */
    public function extractDates(string $text): array
    {
        // Matches YYYY-MM-DD, MM/DD/YYYY, DD-MM-YYYY, etc.
        return $this->extract($text, '/(\d{1,4}[-\/]\d{1,2}[-\/]\d{1,4})/', 1);
    }

    /**
     * Extract code blocks from markdown.
     *
     * @param string $text The markdown text
     * @param string|null $language Optional language filter
     * @return array<string> Code block contents
     */
    public function extractCodeBlocks(string $text, ?string $language = null): array
    {
        if ($language !== null) {
            $pattern = "/```{$language}\\s*([\\s\\S]*?)```/";
        } else {
            $pattern = '/```(?:\w+)?\s*([\s\S]*?)```/';
        }

        return $this->extract($text, $pattern, 1);
    }

    /**
     * Extract hashtags.
     *
     * @param string $text The text to search
     * @return array<string> Found hashtags
     */
    public function extractHashtags(string $text): array
    {
        return $this->extract($text, '/#(\w+)/', 1);
    }

    /**
     * Extract mentions (@username).
     *
     * @param string $text The text to search
     * @return array<string> Found mentions
     */
    public function extractMentions(string $text): array
    {
        return $this->extract($text, '/@(\w+)/', 1);
    }

    /**
     * Get format instructions for the LLM.
     *
     * @return string
     */
    public function getFormatInstructions(): string
    {
        if ($this->defaultPattern !== null) {
            return "Format your response to match this pattern: {$this->defaultPattern}";
        }

        return 'Format your response clearly so specific patterns can be extracted.';
    }

    /**
     * Get parser type.
     *
     * @return string
     */
    public function getType(): string
    {
        return 'regex';
    }
}
