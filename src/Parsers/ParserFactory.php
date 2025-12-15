<?php

declare(strict_types=1);

namespace ClaudeAgents\Parsers;

use ClaudeAgents\Contracts\ParserInterface;

/**
 * Factory for creating and managing parsers.
 *
 * Provides convenient access to all available parsers with
 * optional configuration and custom parser registration.
 */
class ParserFactory
{
    /**
     * @var array<string, class-string<ParserInterface>> Registered parsers
     */
    private array $parsers = [
        'json' => JsonParser::class,
        'list' => ListParser::class,
        'regex' => RegexParser::class,
        'xml' => XmlParser::class,
        'html' => XmlParser::class,
        'markdown' => MarkdownParser::class,
        'md' => MarkdownParser::class,
        'csv' => CsvParser::class,
        'tsv' => CsvParser::class,
    ];

    /**
     * @var array<string, ParserInterface> Cached parser instances
     */
    private array $instances = [];

    /**
     * Get a parser by type.
     *
     * @param string $type Parser type (json, list, regex, xml, markdown, csv, etc.)
     * @throws \InvalidArgumentException If parser type not found
     * @return ParserInterface
     */
    public function get(string $type): ParserInterface
    {
        $type = strtolower($type);

        if (! isset($this->parsers[$type])) {
            throw new \InvalidArgumentException("Unknown parser type: {$type}");
        }

        // Return cached instance if available
        if (isset($this->instances[$type])) {
            return clone $this->instances[$type];
        }

        // Create new instance
        $class = $this->parsers[$type];
        $parser = new $class();

        // Special configuration for certain types
        if ($type === 'html') {
            $parser = $parser->asHtml();
        } elseif ($type === 'tsv') {
            $parser = $parser->asTab();
        } elseif ($type === 'md') {
            // Alias for markdown
            $type = 'markdown';
        }

        $this->instances[$type] = $parser;

        return clone $parser;
    }

    /**
     * Create JSON parser.
     *
     * @param array<string, mixed>|null $schema Optional JSON schema
     * @return JsonParser
     */
    public function json(?array $schema = null): JsonParser
    {
        $parser = new JsonParser();

        if ($schema !== null) {
            $parser->withSchema($schema);
        }

        return $parser;
    }

    /**
     * Create list parser.
     *
     * @param string|null $pattern Optional custom pattern
     * @return ListParser
     */
    public function list(?string $pattern = null): ListParser
    {
        $parser = new ListParser();

        if ($pattern !== null) {
            $parser->withPattern($pattern);
        }

        return $parser;
    }

    /**
     * Create regex parser.
     *
     * @param string|null $pattern Optional pattern
     * @param int $captureGroup Capture group to extract
     * @return RegexParser
     */
    public function regex(?string $pattern = null, int $captureGroup = 1): RegexParser
    {
        $parser = new RegexParser();

        if ($pattern !== null) {
            $parser->withPattern($pattern, $captureGroup);
        }

        return $parser;
    }

    /**
     * Create XML parser.
     *
     * @return XmlParser
     */
    public function xml(): XmlParser
    {
        return new XmlParser();
    }

    /**
     * Create HTML parser.
     *
     * @return XmlParser
     */
    public function html(): XmlParser
    {
        return (new XmlParser())->asHtml();
    }

    /**
     * Create Markdown parser.
     *
     * @return MarkdownParser
     */
    public function markdown(): MarkdownParser
    {
        return new MarkdownParser();
    }

    /**
     * Create CSV parser.
     *
     * @param string $delimiter Field delimiter
     * @param bool $hasHeaders Whether data has headers
     * @return CsvParser
     */
    public function csv(string $delimiter = ',', bool $hasHeaders = true): CsvParser
    {
        $parser = (new CsvParser())->withDelimiter($delimiter);

        if (! $hasHeaders) {
            $parser->withoutHeaders();
        }

        return $parser;
    }

    /**
     * Create TSV parser.
     *
     * @param bool $hasHeaders Whether data has headers
     * @return CsvParser
     */
    public function tsv(bool $hasHeaders = true): CsvParser
    {
        $parser = (new CsvParser())->asTab();

        if (! $hasHeaders) {
            $parser->withoutHeaders();
        }

        return $parser;
    }

    /**
     * Register a custom parser.
     *
     * @param string $type Parser type identifier
     * @param class-string<ParserInterface> $class Parser class name
     * @return self
     */
    public function register(string $type, string $class): self
    {
        if (! is_subclass_of($class, ParserInterface::class)) {
            throw new \InvalidArgumentException(
                'Parser class must implement ' . ParserInterface::class
            );
        }

        $this->parsers[strtolower($type)] = $class;

        // Clear cached instance if exists
        unset($this->instances[strtolower($type)]);

        return $this;
    }

    /**
     * Check if a parser type is registered.
     *
     * @param string $type Parser type
     * @return bool
     */
    public function has(string $type): bool
    {
        return isset($this->parsers[strtolower($type)]);
    }

    /**
     * Get all registered parser types.
     *
     * @return array<string> Parser types
     */
    public function getTypes(): array
    {
        return array_keys($this->parsers);
    }

    /**
     * Auto-detect parser type from text content.
     *
     * @param string $text The text to analyze
     * @return string Detected parser type
     */
    public function detectType(string $text): string
    {
        $trimmed = trim($text);

        // Check for JSON
        if (preg_match('/^\{.*\}$/s', $trimmed) || preg_match('/^\[.*\]$/s', $trimmed)) {
            return 'json';
        }

        // Check for XML/HTML
        if (preg_match('/^<\?xml/', $trimmed) || preg_match('/^<[a-z]+[^>]*>/i', $trimmed)) {
            return preg_match('/^<\?xml/', $trimmed) ? 'xml' : 'html';
        }

        // Check for Markdown headings or code blocks
        if (preg_match('/^#{1,6}\s+/m', $trimmed) || preg_match('/```[\w]*\n/m', $trimmed)) {
            return 'markdown';
        }

        // Check for CSV (comma-separated with consistent columns)
        $lines = explode("\n", $trimmed);
        if (count($lines) >= 2) {
            $firstCommas = substr_count($lines[0], ',');
            $secondCommas = substr_count($lines[1], ',');
            if ($firstCommas > 0 && $firstCommas === $secondCommas) {
                return 'csv';
            }
        }

        // Check for lists
        if (preg_match('/^[-*+]\s+/m', $trimmed) || preg_match('/^\d+\.\s+/m', $trimmed)) {
            return 'list';
        }

        // Default to regex for custom parsing
        return 'regex';
    }

    /**
     * Parse text using auto-detected parser.
     *
     * @param string $text The text to parse
     * @return mixed Parsed result
     */
    public function autoParse(string $text): mixed
    {
        $type = $this->detectType($text);
        $parser = $this->get($type);

        return $parser->parse($text);
    }

    /**
     * Create a singleton instance.
     *
     * @return self
     */
    public static function create(): self
    {
        return new self();
    }
}
