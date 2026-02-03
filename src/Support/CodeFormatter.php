<?php

declare(strict_types=1);

namespace ClaudeAgents\Support;

/**
 * Utilities for formatting and cleaning generated code.
 */
class CodeFormatter
{
    /**
     * Clean and format PHP code.
     *
     * Removes markdown formatting, extra whitespace, and normalizes line endings.
     */
    public static function cleanPhpCode(string $code): string
    {
        // Remove markdown code blocks
        $code = preg_replace('/^```(?:php)?\s*\n/m', '', $code);
        $code = preg_replace('/\n```\s*$/m', '', $code);

        // Trim whitespace
        $code = trim($code);

        // Ensure it starts with <?php
        if (! str_starts_with($code, '<?php')) {
            $code = "<?php\n\n" . $code;
        }

        // Normalize line endings
        $code = str_replace("\r\n", "\n", $code);
        $code = str_replace("\r", "\n", $code);

        // Remove trailing whitespace from lines
        $lines = explode("\n", $code);
        $lines = array_map('rtrim', $lines);
        $code = implode("\n", $lines);

        // Ensure single newline at end
        $code = rtrim($code) . "\n";

        return $code;
    }

    /**
     * Extract PHP code from mixed content (text + code).
     */
    public static function extractPhpCode(string $content): ?string
    {
        // Try to find PHP code in markdown blocks
        if (preg_match('/```(?:php)?\s*\n(.*?)\n```/s', $content, $matches)) {
            return self::cleanPhpCode($matches[1]);
        }

        // Try to find PHP tags
        if (preg_match('/<\?php.*$/s', $content, $matches)) {
            return self::cleanPhpCode($matches[0]);
        }

        return null;
    }

    /**
     * Add line numbers to code for display.
     */
    public static function addLineNumbers(string $code, int $startLine = 1): string
    {
        $lines = explode("\n", $code);
        $width = strlen((string) (count($lines) + $startLine - 1));
        $numbered = [];

        foreach ($lines as $i => $line) {
            $lineNum = str_pad((string) ($i + $startLine), $width, ' ', STR_PAD_LEFT);
            $numbered[] = "{$lineNum} | {$line}";
        }

        return implode("\n", $numbered);
    }

    /**
     * Indent code by a number of spaces.
     */
    public static function indent(string $code, int $spaces): string
    {
        $indent = str_repeat(' ', $spaces);
        $lines = explode("\n", $code);
        $lines = array_map(fn ($line) => empty(trim($line)) ? $line : $indent . $line, $lines);
        return implode("\n", $lines);
    }

    /**
     * Remove indentation from code (dedent).
     */
    public static function dedent(string $code): string
    {
        $lines = explode("\n", $code);

        // Find minimum indentation (excluding empty lines)
        $minIndent = PHP_INT_MAX;
        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }
            $indent = strlen($line) - strlen(ltrim($line));
            $minIndent = min($minIndent, $indent);
        }

        if ($minIndent === PHP_INT_MAX || $minIndent === 0) {
            return $code;
        }

        // Remove minimum indentation from all lines
        $dedented = array_map(function ($line) use ($minIndent) {
            if (empty(trim($line))) {
                return $line;
            }
            return substr($line, $minIndent);
        }, $lines);

        return implode("\n", $dedented);
    }

    /**
     * Format code for display in terminal/console.
     */
    public static function formatForConsole(string $code, bool $addLineNumbers = true): string
    {
        $code = self::cleanPhpCode($code);

        if ($addLineNumbers) {
            $code = self::addLineNumbers($code);
        }

        return $code;
    }

    /**
     * Get code statistics.
     *
     * @return array<string, mixed>
     */
    public static function getStatistics(string $code): array
    {
        $lines = explode("\n", $code);
        $codeLines = 0;
        $commentLines = 0;
        $blankLines = 0;

        $inMultilineComment = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (empty($trimmed)) {
                $blankLines++;
                continue;
            }

            // Check for multiline comments
            if (str_contains($trimmed, '/*')) {
                $inMultilineComment = true;
            }

            if ($inMultilineComment) {
                $commentLines++;
                if (str_contains($trimmed, '*/')) {
                    $inMultilineComment = false;
                }
                continue;
            }

            // Check for single-line comments
            if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '#')) {
                $commentLines++;
                continue;
            }

            $codeLines++;
        }

        return [
            'total_lines' => count($lines),
            'code_lines' => $codeLines,
            'comment_lines' => $commentLines,
            'blank_lines' => $blankLines,
            'bytes' => strlen($code),
            'characters' => mb_strlen($code),
        ];
    }
}
