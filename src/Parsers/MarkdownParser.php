<?php

declare(strict_types=1);

namespace ClaudeAgents\Parsers;

use ClaudeAgents\Contracts\ParserInterface;

/**
 * Parses structured Markdown from LLM responses.
 *
 * Extracts and structures:
 * - Headings (H1-H6)
 * - Code blocks with language tags
 * - Lists (bullet and numbered)
 * - Tables
 * - Links and images
 * - Bold, italic, and other formatting
 */
class MarkdownParser implements ParserInterface
{
    /**
     * @var bool Whether to include raw markdown in output
     */
    private bool $includeRaw = false;

    /**
     * Include raw markdown text in output.
     *
     * @return self
     */
    public function includeRaw(): self
    {
        $this->includeRaw = true;

        return $this;
    }

    /**
     * Parse markdown into structured data.
     *
     * @param string $text The markdown text
     * @return array<string, mixed> Structured markdown data
     */
    public function parse(string $text): array
    {
        $structure = [
            'headings' => $this->extractHeadings($text),
            'code_blocks' => $this->extractCodeBlocks($text),
            'lists' => $this->extractLists($text),
            'links' => $this->extractLinks($text),
            'tables' => $this->extractTables($text),
        ];

        if ($this->includeRaw) {
            $structure['raw'] = $text;
        }

        return $structure;
    }

    /**
     * Extract all headings.
     *
     * @param string $text The markdown text
     * @return array<array{level: int, text: string}> Headings with levels
     */
    public function extractHeadings(string $text): array
    {
        $headings = [];

        if (preg_match_all('/^(#{1,6})\s+(.+)$/m', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $headings[] = [
                    'level' => strlen($match[1]),
                    'text' => trim($match[2]),
                ];
            }
        }

        return $headings;
    }

    /**
     * Extract all code blocks.
     *
     * @param string $text The markdown text
     * @return array<array{language: string|null, code: string}> Code blocks
     */
    public function extractCodeBlocks(string $text): array
    {
        $blocks = [];

        if (preg_match_all('/```(\w*)\n([\s\S]*?)```/', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $blocks[] = [
                    'language' => ! empty($match[1]) ? $match[1] : null,
                    'code' => trim($match[2]),
                ];
            }
        }

        return $blocks;
    }

    /**
     * Extract inline code snippets.
     *
     * @param string $text The markdown text
     * @return array<string> Inline code snippets
     */
    public function extractInlineCode(string $text): array
    {
        if (preg_match_all('/`([^`]+)`/', $text, $matches)) {
            return $matches[1];
        }

        return [];
    }

    /**
     * Extract lists (bullet and numbered).
     *
     * @param string $text The markdown text
     * @return array<array{type: string, items: array<string>}> Lists
     */
    public function extractLists(string $text): array
    {
        $lists = [];
        $lines = explode("\n", $text);
        $currentList = null;

        foreach ($lines as $line) {
            // Bullet list
            if (preg_match('/^[-*+]\s+(.+)$/', trim($line), $match)) {
                if ($currentList === null || $currentList['type'] !== 'bullet') {
                    if ($currentList !== null) {
                        $lists[] = $currentList;
                    }
                    $currentList = ['type' => 'bullet', 'items' => []];
                }
                $currentList['items'][] = $match[1];
            }
            // Numbered list
            elseif (preg_match('/^\d+\.\s+(.+)$/', trim($line), $match)) {
                if ($currentList === null || $currentList['type'] !== 'numbered') {
                    if ($currentList !== null) {
                        $lists[] = $currentList;
                    }
                    $currentList = ['type' => 'numbered', 'items' => []];
                }
                $currentList['items'][] = $match[1];
            }
            // End of list
            elseif (! empty(trim($line))) {
                if ($currentList !== null) {
                    $lists[] = $currentList;
                    $currentList = null;
                }
            }
        }

        if ($currentList !== null) {
            $lists[] = $currentList;
        }

        return $lists;
    }

    /**
     * Extract all links.
     *
     * @param string $text The markdown text
     * @return array<array{text: string, url: string}> Links
     */
    public function extractLinks(string $text): array
    {
        $links = [];

        if (preg_match_all('/\[([^\]]+)\]\(([^\)]+)\)/', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $links[] = [
                    'text' => $match[1],
                    'url' => $match[2],
                ];
            }
        }

        return $links;
    }

    /**
     * Extract images.
     *
     * @param string $text The markdown text
     * @return array<array{alt: string, url: string}> Images
     */
    public function extractImages(string $text): array
    {
        $images = [];

        if (preg_match_all('/!\[([^\]]*)\]\(([^\)]+)\)/', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $images[] = [
                    'alt' => $match[1],
                    'url' => $match[2],
                ];
            }
        }

        return $images;
    }

    /**
     * Extract tables.
     *
     * @param string $text The markdown text
     * @return array<array{headers: array<string>, rows: array<array<string>>}> Tables
     */
    public function extractTables(string $text): array
    {
        $tables = [];
        $lines = explode("\n", $text);
        $i = 0;

        while ($i < count($lines)) {
            $line = trim($lines[$i]);

            // Check if this looks like a table header
            if (str_contains($line, '|')) {
                // Get headers
                $headers = array_map('trim', explode('|', trim($line, '|')));

                // Check for separator line
                if (isset($lines[$i + 1]) && preg_match('/^\|?[\s\-:|]+\|?$/', $lines[$i + 1])) {
                    $rows = [];
                    $j = $i + 2;

                    // Get rows
                    while ($j < count($lines) && str_contains(trim($lines[$j]), '|')) {
                        $row = array_map('trim', explode('|', trim($lines[$j], '|')));
                        if (! empty($row)) {
                            $rows[] = $row;
                        }
                        $j++;
                    }

                    $tables[] = [
                        'headers' => $headers,
                        'rows' => $rows,
                    ];

                    $i = $j;

                    continue;
                }
            }

            $i++;
        }

        return $tables;
    }

    /**
     * Extract text under a specific heading.
     *
     * @param string $text The markdown text
     * @param string $heading The heading text to find
     * @param int|null $level Optional heading level (1-6)
     * @return string Text content under the heading
     */
    public function extractSection(string $text, string $heading, ?int $level = null): string
    {
        $lines = explode("\n", $text);
        $inSection = false;
        $sectionLines = [];

        foreach ($lines as $line) {
            if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $match)) {
                $currentLevel = strlen($match[1]);
                $currentHeading = trim($match[2]);

                if ($currentHeading === $heading && ($level === null || $currentLevel === $level)) {
                    $inSection = true;

                    continue;
                }
                if ($inSection && ($level === null || $currentLevel <= $level)) {
                    // Hit another heading of same or higher level, stop
                    break;
                }
            }

            if ($inSection) {
                $sectionLines[] = $line;
            }
        }

        return trim(implode("\n", $sectionLines));
    }

    /**
     * Strip all markdown formatting, returning plain text.
     *
     * @param string $text The markdown text
     * @return string Plain text
     */
    public function toPlainText(string $text): string
    {
        // Remove code blocks
        $text = preg_replace('/```[\s\S]*?```/', '', $text);

        // Remove inline code
        $text = preg_replace('/`[^`]+`/', '', $text);

        // Remove images
        $text = preg_replace('/!\[([^\]]*)\]\([^\)]+\)/', '$1', $text);

        // Remove links but keep text
        $text = preg_replace('/\[([^\]]+)\]\([^\)]+\)/', '$1', $text);

        // Remove headings markers
        $text = preg_replace('/^#{1,6}\s+/m', '', $text);

        // Remove bold/italic
        $text = preg_replace('/[*_]{1,2}([^*_]+)[*_]{1,2}/', '$1', $text);

        // Remove list markers
        $text = preg_replace('/^[-*+]\s+/m', '', $text);
        $text = preg_replace('/^\d+\.\s+/m', '', $text);

        return trim($text);
    }

    /**
     * Get format instructions for the LLM.
     *
     * @return string
     */
    public function getFormatInstructions(): string
    {
        return "Return your response in well-structured Markdown format.\n\n" .
               "Use:\n" .
               "- # Headings for sections\n" .
               "- ```language code blocks for code\n" .
               "- - bullets or 1. numbered lists\n" .
               "- [text](url) for links\n" .
               '- | tables | with | pipes |';
    }

    /**
     * Get parser type.
     *
     * @return string
     */
    public function getType(): string
    {
        return 'markdown';
    }
}
