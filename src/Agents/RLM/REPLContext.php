<?php

declare(strict_types=1);

namespace ClaudeAgents\Agents\RLM;

/**
 * REPL Context for Recursive Language Model agents.
 *
 * Manages the REPL environment state including variable storage,
 * input metadata, and recursive call tracking. Based on the RLM paper
 * from MIT CSAIL (arXiv:2512.24601v1).
 *
 * The key insight is that long prompts should be treated as part of the
 * external environment that the LLM can symbolically interact with,
 * rather than feeding them directly into the neural network context.
 *
 * @package ClaudeAgents\Agents\RLM
 */
class REPLContext
{
    /**
     * The primary input stored in the REPL environment.
     */
    private string $input;

    /**
     * Variable store for intermediate results.
     *
     * @var array<string, mixed>
     */
    private array $variables = [];

    /**
     * Cached input lines for efficient line-based operations.
     *
     * @var array<int, string>|null
     */
    private ?array $inputLines = null;

    /**
     * Current recursion depth.
     */
    private int $recursionDepth = 0;

    /**
     * Maximum allowed recursion depth.
     */
    private int $maxRecursionDepth;

    /**
     * History of recursive calls for debugging.
     *
     * @var array<array{task: string, depth: int, result: string|null}>
     */
    private array $recursionHistory = [];

    /**
     * Create a new REPL context.
     *
     * @param string $input The input data to store in the environment
     * @param int $maxRecursionDepth Maximum recursion depth (default: 10)
     */
    public function __construct(string $input, int $maxRecursionDepth = 10)
    {
        $this->input = $input;
        $this->maxRecursionDepth = $maxRecursionDepth;
        $this->variables['input'] = $input;
    }

    /**
     * Get the primary input.
     */
    public function getInput(): string
    {
        return $this->input;
    }

    /**
     * Get the character count of the input.
     */
    public function getCharCount(): int
    {
        return strlen($this->input);
    }

    /**
     * Get the line count of the input.
     */
    public function getLineCount(): int
    {
        return count($this->getInputLines());
    }

    /**
     * Get the word count of the input.
     */
    public function getWordCount(): int
    {
        return str_word_count($this->input);
    }

    /**
     * Get the input as an array of lines.
     *
     * @return array<int, string>
     */
    public function getInputLines(): array
    {
        if ($this->inputLines === null) {
            $this->inputLines = explode("\n", $this->input);
        }

        return $this->inputLines;
    }

    /**
     * Peek at a portion of the input by character position.
     *
     * @param int $start Starting character position (0-indexed)
     * @param int $length Number of characters to return
     * @return string The substring
     */
    public function peek(int $start, int $length): string
    {
        $start = max(0, $start);
        $length = max(0, $length);

        return substr($this->input, $start, $length);
    }

    /**
     * Get a slice of the input by line numbers.
     *
     * @param int $startLine Starting line number (1-indexed)
     * @param int $endLine Ending line number (1-indexed, inclusive)
     * @return string The lines joined with newlines
     */
    public function slice(int $startLine, int $endLine): string
    {
        $lines = $this->getInputLines();
        $totalLines = count($lines);

        // Convert to 0-indexed and clamp values
        $startLine = max(1, min($startLine, $totalLines));
        $endLine = max($startLine, min($endLine, $totalLines));

        $startIndex = $startLine - 1;
        $length = $endLine - $startLine + 1;

        $slicedLines = array_slice($lines, $startIndex, $length);

        return implode("\n", $slicedLines);
    }

    /**
     * Search the input using a regex pattern.
     *
     * @param string $pattern Regular expression pattern
     * @param int $contextLines Number of context lines before/after each match
     * @return array<array{line_number: int, line: string, context: string, matches: array<string>}>
     */
    public function search(string $pattern, int $contextLines = 2): array
    {
        $lines = $this->getInputLines();
        $results = [];

        foreach ($lines as $index => $line) {
            if (preg_match($pattern, $line, $matches)) {
                $lineNumber = $index + 1;

                // Get context lines
                $contextStart = max(0, $index - $contextLines);
                $contextEnd = min(count($lines) - 1, $index + $contextLines);
                $contextSlice = array_slice(
                    $lines,
                    $contextStart,
                    $contextEnd - $contextStart + 1
                );

                $results[] = [
                    'line_number' => $lineNumber,
                    'line' => $line,
                    'context' => implode("\n", $contextSlice),
                    'matches' => $matches,
                ];
            }
        }

        return $results;
    }

    /**
     * Get comprehensive metadata about the input.
     *
     * @return array<string, mixed>
     */
    public function getInfo(): array
    {
        $lines = $this->getInputLines();
        $charCount = $this->getCharCount();

        // Get first and last few lines for preview
        $previewLines = 5;
        $firstLines = array_slice($lines, 0, $previewLines);
        $lastLines = count($lines) > $previewLines * 2
            ? array_slice($lines, -$previewLines)
            : [];

        return [
            'char_count' => $charCount,
            'line_count' => count($lines),
            'word_count' => $this->getWordCount(),
            'estimated_tokens' => (int) ($charCount / 4), // Rough estimate
            'first_lines' => implode("\n", $firstLines),
            'last_lines' => implode("\n", $lastLines),
            'variables' => array_keys($this->variables),
            'recursion_depth' => $this->recursionDepth,
            'max_recursion_depth' => $this->maxRecursionDepth,
        ];
    }

    /**
     * Set a variable in the REPL environment.
     *
     * @param string $name Variable name
     * @param mixed $value Variable value
     */
    public function setVariable(string $name, mixed $value): void
    {
        $this->variables[$name] = $value;
    }

    /**
     * Get a variable from the REPL environment.
     *
     * @param string $name Variable name
     * @return mixed|null The variable value or null if not found
     */
    public function getVariable(string $name): mixed
    {
        return $this->variables[$name] ?? null;
    }

    /**
     * Check if a variable exists.
     *
     * @param string $name Variable name
     */
    public function hasVariable(string $name): bool
    {
        return array_key_exists($name, $this->variables);
    }

    /**
     * Get all variable names.
     *
     * @return array<string>
     */
    public function getVariableNames(): array
    {
        return array_keys($this->variables);
    }

    /**
     * Get all variables.
     *
     * @return array<string, mixed>
     */
    public function getVariables(): array
    {
        return $this->variables;
    }

    /**
     * Get current recursion depth.
     */
    public function getRecursionDepth(): int
    {
        return $this->recursionDepth;
    }

    /**
     * Get maximum recursion depth.
     */
    public function getMaxRecursionDepth(): int
    {
        return $this->maxRecursionDepth;
    }

    /**
     * Check if we can recurse deeper.
     */
    public function canRecurse(): bool
    {
        return $this->recursionDepth < $this->maxRecursionDepth;
    }

    /**
     * Enter a recursive call.
     *
     * @param string $task The task being processed recursively
     * @throws \RuntimeException If max recursion depth is exceeded
     */
    public function enterRecursion(string $task): void
    {
        if (!$this->canRecurse()) {
            throw new \RuntimeException(
                "Maximum recursion depth ({$this->maxRecursionDepth}) exceeded"
            );
        }

        $this->recursionDepth++;
        $this->recursionHistory[] = [
            'task' => $task,
            'depth' => $this->recursionDepth,
            'result' => null,
        ];
    }

    /**
     * Exit a recursive call.
     *
     * @param string $result The result of the recursive call
     */
    public function exitRecursion(string $result): void
    {
        if ($this->recursionDepth > 0) {
            $this->recursionDepth--;

            // Update the last history entry with the result
            $lastIndex = count($this->recursionHistory) - 1;
            if ($lastIndex >= 0) {
                $this->recursionHistory[$lastIndex]['result'] = $result;
            }
        }
    }

    /**
     * Get the recursion history.
     *
     * @return array<array{task: string, depth: int, result: string|null}>
     */
    public function getRecursionHistory(): array
    {
        return $this->recursionHistory;
    }

    /**
     * Create a child context for recursive calls.
     *
     * This creates a new context with a portion of the input,
     * inheriting the recursion state.
     *
     * @param string $input The input for the child context
     * @return self
     */
    public function createChildContext(string $input): self
    {
        $child = new self($input, $this->maxRecursionDepth);
        $child->recursionDepth = $this->recursionDepth;
        $child->recursionHistory = $this->recursionHistory;

        return $child;
    }

    /**
     * Generate a summary of the REPL context state.
     */
    public function getSummary(): string
    {
        $info = $this->getInfo();

        return sprintf(
            "REPL Context: %d chars, %d lines, %d words (~%d tokens). " .
            "Variables: [%s]. Recursion: %d/%d",
            $info['char_count'],
            $info['line_count'],
            $info['word_count'],
            $info['estimated_tokens'],
            implode(', ', $info['variables']),
            $info['recursion_depth'],
            $info['max_recursion_depth']
        );
    }
}
