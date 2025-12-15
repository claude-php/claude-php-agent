<?php

declare(strict_types=1);

namespace ClaudeAgents\Tools\BuiltIn;

use ClaudeAgents\Tools\Tool;
use ClaudeAgents\Tools\ToolResult;

/**
 * Regex tool for pattern matching and text operations.
 */
class RegexTool
{
    /**
     * Create a regex tool.
     *
     * @param array{
     *     max_text_length?: int,
     *     max_matches?: int
     * } $config Configuration options
     */
    public static function create(array $config = []): Tool
    {
        $maxTextLength = $config['max_text_length'] ?? 100000; // 100KB default
        $maxMatches = $config['max_matches'] ?? 1000;

        return Tool::create('regex')
            ->description(
                'Perform regex operations: match patterns, extract data, replace text, split strings, and validate patterns. ' .
                'Supports all PHP regex patterns (PCRE).'
            )
            ->stringParam(
                'operation',
                'Operation to perform',
                true,
                ['match', 'match_all', 'replace', 'split', 'test', 'extract']
            )
            ->stringParam('pattern', 'Regex pattern (e.g., "/\d+/", "/[a-z]+/i")')
            ->stringParam('text', 'Text to operate on')
            ->stringParam('replacement', 'Replacement string (for replace operation)', false)
            ->numberParam('limit', 'Maximum number of matches/replacements', false, -1, $maxMatches)
            ->handler(function (array $input) use ($maxTextLength, $maxMatches): ToolResult {
                $operation = $input['operation'];
                $pattern = $input['pattern'] ?? '';
                $text = $input['text'] ?? '';
                $replacement = $input['replacement'] ?? '';
                $limit = (int) ($input['limit'] ?? -1);

                if (empty($pattern)) {
                    return ToolResult::error('Pattern parameter is required');
                }

                if (empty($text) && $operation !== 'test') {
                    return ToolResult::error('Text parameter is required');
                }

                // Security: Check text length
                if (strlen($text) > $maxTextLength) {
                    return ToolResult::error(
                        'Text too long: ' . strlen($text) . " bytes (max: {$maxTextLength})"
                    );
                }

                // Validate pattern
                if (@preg_match($pattern, '') === false) {
                    $error = error_get_last();

                    return ToolResult::error(
                        'Invalid regex pattern: ' . ($error['message'] ?? 'unknown error')
                    );
                }

                try {
                    switch ($operation) {
                        case 'match':
                            // Find first match
                            $matches = [];
                            $result = preg_match($pattern, $text, $matches);

                            if ($result === false) {
                                return ToolResult::error('Regex execution failed');
                            }

                            return ToolResult::success([
                                'matched' => $result === 1,
                                'matches' => $matches,
                                'count' => $result,
                            ]);

                        case 'match_all':
                            // Find all matches
                            $matches = [];
                            $flags = PREG_SET_ORDER;
                            $result = preg_match_all($pattern, $text, $matches, $flags);

                            if ($result === false) {
                                return ToolResult::error('Regex execution failed');
                            }

                            // Apply limit if specified
                            if ($limit > 0 && count($matches) > $limit) {
                                $matches = array_slice($matches, 0, $limit);
                            }

                            return ToolResult::success([
                                'matched' => $result > 0,
                                'matches' => $matches,
                                'count' => $result,
                                'limited' => $limit > 0 && $result > $limit,
                            ]);

                        case 'replace':
                            if (empty($replacement) && $replacement !== '0') {
                                return ToolResult::error('Replacement parameter is required for replace operation');
                            }

                            $count = 0;
                            $result = preg_replace($pattern, $replacement, $text, $limit, $count);

                            if ($result === null) {
                                return ToolResult::error('Regex replacement failed');
                            }

                            return ToolResult::success([
                                'result' => $result,
                                'replacements' => $count,
                                'original_length' => strlen($text),
                                'new_length' => strlen($result),
                            ]);

                        case 'split':
                            $result = preg_split($pattern, $text, $limit > 0 ? $limit : -1);

                            if ($result === false) {
                                return ToolResult::error('Regex split failed');
                            }

                            return ToolResult::success([
                                'parts' => $result,
                                'count' => count($result),
                            ]);

                        case 'test':
                            // Just test if pattern is valid
                            $isValid = @preg_match($pattern, '') !== false;

                            if (! $isValid) {
                                $error = error_get_last();

                                return ToolResult::success([
                                    'valid' => false,
                                    'error' => $error['message'] ?? 'unknown error',
                                ]);
                            }

                            return ToolResult::success([
                                'valid' => true,
                                'pattern' => $pattern,
                            ]);

                        case 'extract':
                            // Extract specific capturing groups
                            $matches = [];
                            $result = preg_match_all($pattern, $text, $matches, PREG_SET_ORDER);

                            if ($result === false) {
                                return ToolResult::error('Regex extraction failed');
                            }

                            // Extract only captured groups (not the full match)
                            $extracted = [];
                            foreach ($matches as $match) {
                                if (count($match) > 1) {
                                    // Remove first element (full match), keep only captured groups
                                    array_shift($match);
                                    $extracted[] = $match;
                                }
                            }

                            // Apply limit if specified
                            if ($limit > 0 && count($extracted) > $limit) {
                                $extracted = array_slice($extracted, 0, $limit);
                            }

                            return ToolResult::success([
                                'extracted' => $extracted,
                                'count' => count($extracted),
                                'limited' => $limit > 0 && $result > $limit,
                            ]);

                        default:
                            return ToolResult::error("Unknown operation: {$operation}");
                    }
                } catch (\Throwable $e) {
                    return ToolResult::error("Regex error: {$e->getMessage()}");
                }
            });
    }
}
