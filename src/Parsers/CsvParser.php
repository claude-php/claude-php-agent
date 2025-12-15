<?php

declare(strict_types=1);

namespace ClaudeAgents\Parsers;

use ClaudeAgents\Contracts\ParserInterface;

/**
 * Parses CSV/TSV data from LLM responses.
 *
 * Features:
 * - Configurable delimiters and enclosures
 * - Header detection and mapping
 * - Type conversion (strings, numbers, booleans)
 * - Handle quoted fields and escaped characters
 */
class CsvParser implements ParserInterface
{
    /**
     * @var string Field delimiter
     */
    private string $delimiter = ',';

    /**
     * @var string Field enclosure character
     */
    private string $enclosure = '"';

    /**
     * @var string Escape character
     */
    private string $escape = '\\';

    /**
     * @var bool Whether first row is headers
     */
    private bool $hasHeaders = true;

    /**
     * @var bool Whether to convert types (string numbers to int/float)
     */
    private bool $convertTypes = false;

    /**
     * Set delimiter character.
     *
     * @param string $delimiter Delimiter (default: ',')
     * @return self
     */
    public function withDelimiter(string $delimiter): self
    {
        $this->delimiter = $delimiter;

        return $this;
    }

    /**
     * Use tab delimiter (TSV).
     *
     * @return self
     */
    public function asTab(): self
    {
        $this->delimiter = "\t";

        return $this;
    }

    /**
     * Use semicolon delimiter.
     *
     * @return self
     */
    public function asSemicolon(): self
    {
        $this->delimiter = ';';

        return $this;
    }

    /**
     * Set enclosure character.
     *
     * @param string $enclosure Enclosure character
     * @return self
     */
    public function withEnclosure(string $enclosure): self
    {
        $this->enclosure = $enclosure;

        return $this;
    }

    /**
     * Data has no header row.
     *
     * @return self
     */
    public function withoutHeaders(): self
    {
        $this->hasHeaders = false;

        return $this;
    }

    /**
     * Convert string values to appropriate types.
     *
     * @return self
     */
    public function withTypeConversion(): self
    {
        $this->convertTypes = true;

        return $this;
    }

    /**
     * Parse CSV data.
     *
     * @param string $text The CSV text
     * @throws \RuntimeException If parsing fails
     * @return array<array<string, mixed>> Parsed rows
     */
    public function parse(string $text): array
    {
        // Extract CSV from markdown code blocks if present
        $csv = $this->extractCsv($text);

        if (empty(trim($csv))) {
            throw new \RuntimeException('No CSV data found');
        }

        $rows = [];
        $lines = explode("\n", $csv);
        $headers = null;

        foreach ($lines as $index => $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $fields = $this->parseLine($line);

            if ($this->hasHeaders && $headers === null) {
                $headers = $fields;

                continue;
            }

            if ($this->hasHeaders && $headers !== null) {
                $row = [];
                foreach ($fields as $i => $value) {
                    $key = $headers[$i] ?? "column_{$i}";
                    $row[$key] = $this->convertTypes ? $this->convertType($value) : $value;
                }
                $rows[] = $row;
            } else {
                $rows[] = $this->convertTypes ? array_map([$this, 'convertType'], $fields) : $fields;
            }
        }

        return $rows;
    }

    /**
     * Extract CSV from text (handles code blocks).
     *
     * @param string $text The text to extract from
     * @return string The extracted CSV
     */
    private function extractCsv(string $text): string
    {
        // Try to find CSV in code blocks
        if (preg_match('/```(?:csv|tsv)\s*([\s\S]*?)\s*```/', $text, $matches)) {
            return $matches[1];
        }

        // Return as-is
        return $text;
    }

    /**
     * Parse a single CSV line.
     *
     * @param string $line The line to parse
     * @return array<string> Fields
     */
    private function parseLine(string $line): array
    {
        $fields = [];
        $field = '';
        $inQuotes = false;
        $length = strlen($line);

        for ($i = 0; $i < $length; $i++) {
            $char = $line[$i];

            if ($char === $this->escape && $i + 1 < $length) {
                // Escaped character
                $field .= $line[$i + 1];
                $i++;
            } elseif ($char === $this->enclosure) {
                // Toggle quote state
                $inQuotes = ! $inQuotes;
            } elseif ($char === $this->delimiter && ! $inQuotes) {
                // End of field
                $fields[] = trim($field);
                $field = '';
            } else {
                $field .= $char;
            }
        }

        // Add last field
        $fields[] = trim($field);

        return $fields;
    }

    /**
     * Convert string value to appropriate type.
     *
     * @param string $value The value to convert
     * @return mixed Converted value
     */
    private function convertType(string $value): mixed
    {
        // Empty strings
        if ($value === '') {
            return null;
        }

        // Booleans
        $lower = strtolower($value);
        if ($lower === 'true' || $lower === 'yes' || $lower === '1') {
            return true;
        }
        if ($lower === 'false' || $lower === 'no' || $lower === '0') {
            return false;
        }

        // Numbers
        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return $value;
    }

    /**
     * Get format instructions for the LLM.
     *
     * @return string
     */
    public function getFormatInstructions(): string
    {
        $delimiter = $this->delimiter === "\t" ? 'tab' : $this->delimiter;
        $example = $this->hasHeaders ? "Name,Age,City\nJohn,30,NYC\nJane,25,LA" : "John,30,NYC\nJane,25,LA";

        return "Return your response as CSV data using '{$delimiter}' as delimiter.\n\n" .
               "Example:\n```csv\n{$example}\n```";
    }

    /**
     * Get parser type.
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->delimiter === "\t" ? 'tsv' : 'csv';
    }

    /**
     * Convert array back to CSV string.
     *
     * @param array<array<string, mixed>> $data Data to convert
     * @param bool $includeHeaders Whether to include headers
     * @return string CSV string
     */
    public function toCsv(array $data, bool $includeHeaders = true): string
    {
        if (empty($data)) {
            return '';
        }

        $lines = [];

        // Add headers if needed
        if ($includeHeaders && is_array($data[0]) && ! isset($data[0][0])) {
            $headers = array_keys($data[0]);
            $lines[] = $this->formatLine($headers);
        }

        // Add data rows
        foreach ($data as $row) {
            if (is_array($row)) {
                $lines[] = $this->formatLine(array_values($row));
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Format a line as CSV.
     *
     * @param array<mixed> $fields Fields to format
     * @return string Formatted line
     */
    private function formatLine(array $fields): string
    {
        $formatted = [];

        foreach ($fields as $field) {
            $value = (string) $field;

            // Quote if contains delimiter, quotes, or newlines
            if (str_contains($value, $this->delimiter) ||
                str_contains($value, $this->enclosure) ||
                str_contains($value, "\n")) {
                $value = $this->enclosure .
                         str_replace($this->enclosure, $this->escape . $this->enclosure, $value) .
                         $this->enclosure;
            }

            $formatted[] = $value;
        }

        return implode($this->delimiter, $formatted);
    }
}
