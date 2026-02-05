<?php

declare(strict_types=1);

namespace ClaudeAgents\Skills;

use ClaudeAgents\Skills\Exceptions\SkillValidationException;

/**
 * Parses YAML frontmatter from SKILL.md files.
 *
 * Handles the standard frontmatter format:
 * ---
 * name: skill-name
 * description: What this skill does
 * ---
 *
 * Supports nested YAML values (metadata, dependencies, compatibility).
 */
class FrontmatterParser
{
    /**
     * Parse a SKILL.md file content into frontmatter and body.
     *
     * @param string $content Raw SKILL.md content
     * @return array{frontmatter: array, body: string}
     * @throws SkillValidationException If frontmatter is missing or invalid
     */
    public static function parse(string $content): array
    {
        $content = ltrim($content);

        // Must start with ---
        if (!str_starts_with($content, '---')) {
            throw new SkillValidationException(
                'SKILL.md must start with YAML frontmatter (---)'
            );
        }

        // Find closing ---
        $secondDelimiter = strpos($content, '---', 3);
        if ($secondDelimiter === false) {
            throw new SkillValidationException(
                'SKILL.md frontmatter must be closed with ---'
            );
        }

        $yamlContent = substr($content, 3, $secondDelimiter - 3);
        $body = trim(substr($content, $secondDelimiter + 3));

        $frontmatter = self::parseYaml($yamlContent);

        return [
            'frontmatter' => $frontmatter,
            'body' => $body,
        ];
    }

    /**
     * Generate frontmatter string from an array.
     *
     * @param array $data Frontmatter data
     * @return string YAML frontmatter string with delimiters
     */
    public static function generate(array $data): string
    {
        $yaml = self::arrayToYaml($data);

        return "---\n" . $yaml . "---\n";
    }

    /**
     * Parse simple YAML content without requiring external dependencies.
     *
     * Supports: strings, numbers, booleans, arrays, nested objects.
     */
    private static function parseYaml(string $yaml): array
    {
        $result = [];
        $lines = explode("\n", $yaml);
        $currentKey = null;
        $currentIndent = 0;
        $nestedLines = [];
        $inMultiline = false;
        $multilineKey = null;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Skip empty lines and comments
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                if ($inMultiline && $trimmed === '') {
                    $nestedLines[] = '';
                }
                continue;
            }

            // Calculate indentation
            $indent = strlen($line) - strlen(ltrim($line));

            // Handle nested content collection
            if ($inMultiline && $indent > $currentIndent) {
                $nestedLines[] = $line;
                continue;
            } elseif ($inMultiline) {
                // Process collected nested content
                $result[$multilineKey] = self::parseNestedYaml($nestedLines, $currentIndent + 2);
                $inMultiline = false;
                $nestedLines = [];
            }

            // Parse key-value pair
            if (preg_match('/^(\s*)([a-zA-Z0-9_-]+)\s*:\s*(.*)$/', $line, $matches)) {
                $key = $matches[2];
                $value = trim($matches[3]);

                if ($value === '') {
                    // Start of nested object
                    $inMultiline = true;
                    $multilineKey = $key;
                    $currentIndent = $indent;
                    $nestedLines = [];
                } else {
                    $result[$key] = self::parseValue($value);
                }
            }
        }

        // Handle any remaining nested content
        if ($inMultiline && !empty($nestedLines)) {
            $result[$multilineKey] = self::parseNestedYaml($nestedLines, $currentIndent + 2);
        }

        return $result;
    }

    /**
     * Parse nested YAML content.
     */
    private static function parseNestedYaml(array $lines, int $baseIndent): array
    {
        $result = [];
        $isArray = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            // Array item
            if (str_starts_with($trimmed, '- ')) {
                $isArray = true;
                $value = trim(substr($trimmed, 2));

                // Check if it's a key-value in array
                if (preg_match('/^([a-zA-Z0-9_-]+)\s*:\s*(.+)$/', $value, $matches)) {
                    $result[] = [$matches[1] => self::parseValue(trim($matches[2]))];
                } else {
                    $result[] = self::parseValue($value);
                }
                continue;
            }

            // Key-value pair
            if (preg_match('/^([a-zA-Z0-9_-]+)\s*:\s*(.*)$/', $trimmed, $matches)) {
                $key = $matches[1];
                $value = trim($matches[2]);
                $result[$key] = $value !== '' ? self::parseValue($value) : '';
            }
        }

        return $result;
    }

    /**
     * Parse a YAML scalar value.
     */
    private static function parseValue(string $value): mixed
    {
        // Quoted strings
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            return substr($value, 1, -1);
        }

        // Booleans
        $lower = strtolower($value);
        if ($lower === 'true' || $lower === 'yes') {
            return true;
        }
        if ($lower === 'false' || $lower === 'no') {
            return false;
        }

        // Null
        if ($lower === 'null' || $lower === '~') {
            return null;
        }

        // Integers
        if (preg_match('/^-?\d+$/', $value)) {
            return (int) $value;
        }

        // Floats
        if (preg_match('/^-?\d+\.\d+$/', $value)) {
            return (float) $value;
        }

        // Inline arrays [a, b, c]
        if (str_starts_with($value, '[') && str_ends_with($value, ']')) {
            $inner = substr($value, 1, -1);
            return array_map(function ($item) {
                return self::parseValue(trim($item));
            }, explode(',', $inner));
        }

        return $value;
    }

    /**
     * Convert array to YAML string.
     */
    private static function arrayToYaml(array $data, int $indent = 0): string
    {
        $yaml = '';
        $prefix = str_repeat('  ', $indent);

        foreach ($data as $key => $value) {
            if (is_int($key)) {
                // Array item
                if (is_array($value)) {
                    $yaml .= $prefix . "- " . self::arrayToYaml($value, $indent + 1);
                } else {
                    $yaml .= $prefix . "- " . self::valueToYaml($value) . "\n";
                }
            } elseif (is_array($value)) {
                // Use inline format for simple scalar arrays to avoid deep nesting
                if (self::isSimpleScalarArray($value)) {
                    $items = array_map(fn($v) => self::valueToYaml($v), $value);
                    $yaml .= $prefix . $key . ': [' . implode(', ', $items) . "]\n";
                } else {
                    $yaml .= $prefix . $key . ":\n";
                    $yaml .= self::arrayToYaml($value, $indent + 1);
                }
            } else {
                $yaml .= $prefix . $key . ': ' . self::valueToYaml($value) . "\n";
            }
        }

        return $yaml;
    }

    /**
     * Check if an array contains only scalar values with sequential integer keys.
     */
    private static function isSimpleScalarArray(array $array): bool
    {
        if (empty($array)) {
            return true;
        }

        $i = 0;
        foreach ($array as $key => $value) {
            if ($key !== $i || is_array($value)) {
                return false;
            }
            $i++;
        }

        return true;
    }

    /**
     * Convert a scalar value to YAML representation.
     */
    private static function valueToYaml(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_string($value)) {
            // Quote if contains special chars
            if (preg_match('/[:#\[\]{}|>!@%&*?]/', $value) || str_contains($value, "\n")) {
                return '"' . addslashes($value) . '"';
            }
            return $value;
        }

        return (string) $value;
    }
}
