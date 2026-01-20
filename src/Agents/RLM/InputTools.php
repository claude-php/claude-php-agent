<?php

declare(strict_types=1);

namespace ClaudeAgents\Agents\RLM;

use ClaudeAgents\Tools\Tool;
use ClaudeAgents\Tools\ToolResult;

/**
 * Factory for creating RLM input manipulation tools.
 *
 * These tools allow the LLM to interact with the input stored in the
 * REPL environment without loading it into the context window. This
 * enables processing of inputs that exceed the context window size.
 *
 * Based on the RLM paper from MIT CSAIL (arXiv:2512.24601v1).
 *
 * @package ClaudeAgents\Agents\RLM
 */
class InputTools
{
    /**
     * Create the peek_input tool.
     *
     * Allows viewing a substring of the input by character position.
     *
     * @param REPLContext $context The REPL context
     * @return Tool
     */
    public static function peek(REPLContext $context): Tool
    {
        return Tool::create('peek_input')
            ->description(
                'View a portion of the input by character position. ' .
                'Use this to examine specific parts of the input without loading the entire content. ' .
                'The input has ' . $context->getCharCount() . ' characters total.'
            )
            ->numberParam('start', 'Starting character position (0-indexed)', true, 0)
            ->numberParam('length', 'Number of characters to return (max 10000)', true, 1, 10000)
            ->handler(function (array $input) use ($context): ToolResult {
                $start = (int) ($input['start'] ?? 0);
                $length = min((int) ($input['length'] ?? 1000), 10000);

                $content = $context->peek($start, $length);

                return ToolResult::success([
                    'content' => $content,
                    'start' => $start,
                    'length' => strlen($content),
                    'total_chars' => $context->getCharCount(),
                    'has_more' => ($start + strlen($content)) < $context->getCharCount(),
                ]);
            });
    }

    /**
     * Create the slice_input tool.
     *
     * Allows extracting a range of lines from the input.
     *
     * @param REPLContext $context The REPL context
     * @return Tool
     */
    public static function slice(REPLContext $context): Tool
    {
        return Tool::create('slice_input')
            ->description(
                'Extract a range of lines from the input. ' .
                'Use this for line-based navigation of the input. ' .
                'The input has ' . $context->getLineCount() . ' lines total.'
            )
            ->numberParam('start_line', 'Starting line number (1-indexed)', true, 1)
            ->numberParam('end_line', 'Ending line number (1-indexed, inclusive)', true, 1)
            ->handler(function (array $input) use ($context): ToolResult {
                $startLine = (int) ($input['start_line'] ?? 1);
                $endLine = (int) ($input['end_line'] ?? $startLine + 50);

                // Limit the range to prevent excessive output
                $maxLines = 200;
                if (($endLine - $startLine + 1) > $maxLines) {
                    $endLine = $startLine + $maxLines - 1;
                }

                $content = $context->slice($startLine, $endLine);

                return ToolResult::success([
                    'content' => $content,
                    'start_line' => $startLine,
                    'end_line' => min($endLine, $context->getLineCount()),
                    'lines_returned' => count(explode("\n", $content)),
                    'total_lines' => $context->getLineCount(),
                ]);
            });
    }

    /**
     * Create the search_input tool.
     *
     * Allows searching the input using regex patterns.
     *
     * @param REPLContext $context The REPL context
     * @return Tool
     */
    public static function search(REPLContext $context): Tool
    {
        return Tool::create('search_input')
            ->description(
                'Search the input using a regular expression pattern. ' .
                'Returns matching lines with context. Use this to find specific content without reading everything.'
            )
            ->stringParam('pattern', 'Regular expression pattern (e.g., "/error/i" for case-insensitive match)')
            ->numberParam('context_lines', 'Number of context lines before/after each match (default: 2)', false, 0, 10)
            ->numberParam('max_results', 'Maximum number of results to return (default: 20)', false, 1, 100)
            ->handler(function (array $input) use ($context): ToolResult {
                $pattern = $input['pattern'] ?? '';
                $contextLines = (int) ($input['context_lines'] ?? 2);
                $maxResults = (int) ($input['max_results'] ?? 20);

                if (empty($pattern)) {
                    return ToolResult::error('Pattern parameter is required');
                }

                // Validate regex pattern
                if (@preg_match($pattern, '') === false) {
                    return ToolResult::error('Invalid regular expression pattern: ' . $pattern);
                }

                try {
                    $results = $context->search($pattern, $contextLines);

                    // Limit results
                    $truncated = count($results) > $maxResults;
                    $results = array_slice($results, 0, $maxResults);

                    return ToolResult::success([
                        'matches' => $results,
                        'match_count' => count($results),
                        'truncated' => $truncated,
                        'total_lines' => $context->getLineCount(),
                    ]);
                } catch (\Throwable $e) {
                    return ToolResult::error('Search error: ' . $e->getMessage());
                }
            });
    }

    /**
     * Create the get_input_info tool.
     *
     * Returns metadata about the input.
     *
     * @param REPLContext $context The REPL context
     * @return Tool
     */
    public static function info(REPLContext $context): Tool
    {
        return Tool::create('get_input_info')
            ->description(
                'Get comprehensive metadata about the input stored in the REPL environment. ' .
                'Returns character count, line count, word count, estimated tokens, ' .
                'first/last lines preview, and available variables.'
            )
            ->handler(function (array $input) use ($context): ToolResult {
                return ToolResult::success($context->getInfo());
            });
    }

    /**
     * Create the set_variable tool.
     *
     * Allows storing intermediate results in the REPL environment.
     *
     * @param REPLContext $context The REPL context
     * @return Tool
     */
    public static function setVariable(REPLContext $context): Tool
    {
        return Tool::create('set_variable')
            ->description(
                'Store an intermediate result in the REPL environment. ' .
                'Use this to save extracted data, partial results, or computed values for later use.'
            )
            ->stringParam('name', 'Variable name (alphanumeric and underscores)')
            ->stringParam('value', 'Value to store (will be JSON encoded if not a string)')
            ->handler(function (array $input) use ($context): ToolResult {
                $name = $input['name'] ?? '';
                $value = $input['value'] ?? '';

                if (empty($name)) {
                    return ToolResult::error('Variable name is required');
                }

                // Validate variable name
                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
                    return ToolResult::error(
                        'Invalid variable name. Use alphanumeric characters and underscores, ' .
                        'starting with a letter or underscore.'
                    );
                }

                // Protect reserved variables
                if ($name === 'input') {
                    return ToolResult::error('Cannot overwrite the reserved "input" variable');
                }

                $context->setVariable($name, $value);

                return ToolResult::success([
                    'stored' => true,
                    'name' => $name,
                    'value_length' => strlen((string) $value),
                    'variables' => $context->getVariableNames(),
                ]);
            });
    }

    /**
     * Create the get_variable tool.
     *
     * Retrieves a stored variable from the REPL environment.
     *
     * @param REPLContext $context The REPL context
     * @return Tool
     */
    public static function getVariable(REPLContext $context): Tool
    {
        return Tool::create('get_variable')
            ->description(
                'Retrieve a stored variable from the REPL environment. ' .
                'Use this to access previously saved intermediate results.'
            )
            ->stringParam('name', 'Variable name to retrieve')
            ->handler(function (array $input) use ($context): ToolResult {
                $name = $input['name'] ?? '';

                if (empty($name)) {
                    return ToolResult::error('Variable name is required');
                }

                if (!$context->hasVariable($name)) {
                    return ToolResult::error(
                        "Variable '{$name}' not found. Available: " .
                        implode(', ', $context->getVariableNames())
                    );
                }

                $value = $context->getVariable($name);

                // Truncate very long values
                $valueStr = is_string($value) ? $value : json_encode($value);
                $truncated = strlen($valueStr) > 10000;
                if ($truncated) {
                    $valueStr = substr($valueStr, 0, 10000) . '... (truncated)';
                }

                return ToolResult::success([
                    'name' => $name,
                    'value' => $valueStr,
                    'truncated' => $truncated,
                ]);
            });
    }

    /**
     * Get all standard input tools for an RLM agent.
     *
     * @param REPLContext $context The REPL context
     * @return array<Tool>
     */
    public static function all(REPLContext $context): array
    {
        return [
            self::peek($context),
            self::slice($context),
            self::search($context),
            self::info($context),
            self::setVariable($context),
            self::getVariable($context),
        ];
    }
}
