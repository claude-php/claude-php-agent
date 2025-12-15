<?php

declare(strict_types=1);

namespace ClaudeAgents\Helpers;

use ClaudePhp\ClaudePhp;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Shared Helper Functions for AI Agents
 *
 * This class provides common utilities used across agent implementations to avoid code duplication
 * and provide consistent patterns for building AI agents.
 */
class AgentHelpers
{
    /**
     * Run an agent loop with configurable iteration limits
     *
     * This is the core ReAct loop implementation that powers autonomous agents.
     * It continues calling Claude and executing tools until:
     * - Claude provides a final answer (stop_reason = 'end_turn')
     * - Maximum iterations reached
     * - An error occurs
     *
     * @param ClaudePhp $client The Claude client instance
     * @param array $messages Current conversation history
     * @param array $tools Available tools for the agent
     * @param callable|null $toolExecutor Callback to execute tools: fn(string $name, array $input): string
     * @param array $config Configuration options:
     *   - max_iterations: Maximum loop iterations (default: 10)
     *   - model: Model to use (default: claude-sonnet-4-20250514)
     *   - max_tokens: Max tokens per response (default: 4096)
     *   - debug: Show debug output (default: false)
     *   - system: System prompt (optional)
     *   - thinking: Enable extended thinking (optional)
     *   - logger: PSR-3 logger instance (optional, defaults to NullLogger)
     *
     * @return array Result with keys: success, response, messages, iterations, error
     */
    public static function runAgentLoop(
        ClaudePhp $client,
        array $messages,
        array $tools,
        ?callable $toolExecutor = null,
        array $config = []
    ): array {
        $maxIterations = $config['max_iterations'] ?? 10;
        $model = $config['model'] ?? 'claude-sonnet-4-20250514';
        $maxTokens = $config['max_tokens'] ?? 4096;
        $debug = $config['debug'] ?? false;
        $system = $config['system'] ?? null;
        $thinking = $config['thinking'] ?? null;
        $logger = $config['logger'] ?? new NullLogger();

        $iteration = 0;
        $finalResponse = null;

        while ($iteration < $maxIterations) {
            $iteration++;

            if ($debug) {
                $logger->debug("Agent Iteration {$iteration}/{$maxIterations}");
            }

            // Build request parameters
            $params = [
                'model' => $model,
                'max_tokens' => $maxTokens,
                'messages' => $messages,
                'tools' => $tools,
            ];

            if ($system) {
                $params['system'] = $system;
            }

            if ($thinking) {
                $params['thinking'] = $thinking;
            }

            // Call Claude
            try {
                $response = $client->messages()->create($params);
            } catch (Exception $e) {
                $logger->error('Agent iteration failed', [
                    'iteration' => $iteration,
                    'error' => $e->getMessage(),
                ]);

                return [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'messages' => $messages,
                    'iterations' => $iteration,
                    'response' => null,
                ];
            }

            if ($debug) {
                self::debugAgentStep($response, $iteration, $logger);
            }

            // Add assistant response to conversation
            $messages[] = [
                'role' => 'assistant',
                'content' => $response->content,
            ];

            // Check if we're done
            if ($response->stop_reason === 'end_turn') {
                $finalResponse = $response;
                $logger->info('Agent completed successfully', ['iterations' => $iteration]);

                break;
            }

            // Extract and execute tool calls
            if ($response->stop_reason === 'tool_use') {
                $toolUses = self::extractToolUses($response);

                if (empty($toolUses)) {
                    $logger->warning("stop_reason='tool_use' but no tool uses found");

                    break;
                }

                // Execute each tool and collect results
                $toolResults = [];
                foreach ($toolUses as $toolUse) {
                    if ($debug) {
                        $logger->debug('Executing tool', [
                            'tool' => $toolUse['name'],
                            'input' => $toolUse['input'],
                        ]);
                    }

                    // Execute tool if executor provided
                    if ($toolExecutor) {
                        try {
                            $result = $toolExecutor($toolUse['name'], $toolUse['input']);
                            $toolResults[] = self::formatToolResult($toolUse['id'], $result, false);

                            if ($debug) {
                                $logger->debug('Tool execution result', [
                                    'tool' => $toolUse['name'],
                                    'result' => is_string($result) ? substr($result, 0, 100) : json_encode($result),
                                ]);
                            }
                        } catch (Exception $e) {
                            $toolResults[] = self::formatToolResult($toolUse['id'], 'Error: ' . $e->getMessage(), true);
                            $logger->error('Tool execution failed', [
                                'tool' => $toolUse['name'],
                                'error' => $e->getMessage(),
                            ]);
                        }
                    } else {
                        // No executor provided
                        $toolResults[] = [
                            'type' => 'tool_result',
                            'tool_use_id' => $toolUse['id'],
                            'content' => '[Tool executor not configured]',
                        ];
                    }
                }

                // Add tool results to conversation
                $messages[] = [
                    'role' => 'user',
                    'content' => $toolResults,
                ];
            } else {
                // Unexpected stop reason
                $logger->warning('Unexpected stop_reason', ['stop_reason' => $response->stop_reason]);
                $finalResponse = $response;

                break;
            }
        }

        if ($iteration >= $maxIterations && ! $finalResponse) {
            $logger->warning('Max iterations reached without completion', ['iterations' => $iteration]);
        }

        return [
            'success' => $finalResponse !== null,
            'response' => $finalResponse,
            'messages' => $messages,
            'iterations' => $iteration,
            'error' => null,
        ];
    }

    /**
     * Extract tool use blocks from a Claude response
     *
     * @param mixed $response Claude API response
     * @return array Array of tool use blocks with keys: type, id, name, input
     */
    public static function extractToolUses($response): array
    {
        $toolUses = [];

        if (! isset($response->content)) {
            return $toolUses;
        }

        foreach ($response->content as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'tool_use') {
                $toolUses[] = $block;
            } elseif (is_object($block) && isset($block->type) && $block->type === 'tool_use') {
                // Handle object format (Dynamic properties access on API response)
                $toolUses[] = [
                    'type' => 'tool_use',
                    /** @phpstan-ignore property.notFound */
                    'id' => $block->id,
                    /** @phpstan-ignore property.notFound */
                    'name' => $block->name,
                    /** @phpstan-ignore property.notFound */
                    'input' => (array)$block->input,
                ];
            }
        }

        return $toolUses;
    }

    /**
     * Format a tool execution result for returning to Claude
     *
     * @param string $toolUseId The tool_use_id from Claude's request
     * @param mixed $result The result from executing the tool
     * @param bool $isError Whether this is an error result
     * @return array Formatted tool_result block
     */
    public static function formatToolResult(string $toolUseId, $result, bool $isError = false): array
    {
        $toolResult = [
            'type' => 'tool_result',
            'tool_use_id' => $toolUseId,
            'content' => is_string($result) ? $result : json_encode($result),
        ];

        if ($isError) {
            $toolResult['is_error'] = true;
        }

        return $toolResult;
    }

    /**
     * Debug output for agent reasoning steps
     *
     * Shows what Claude is thinking and planning to do
     *
     * @param mixed $response Claude API response
     * @param int $iteration Current iteration number
     * @param LoggerInterface|null $logger Optional logger (defaults to NullLogger)
     */
    public static function debugAgentStep($response, int $iteration, ?LoggerInterface $logger = null): void
    {
        $logger ??= new NullLogger();

        $logger->debug("Step {$iteration} Analysis");

        if (! isset($response->content)) {
            $logger->debug('No content in response');

            return;
        }

        // Show thinking if present
        foreach ($response->content as $block) {
            if (is_array($block)) {
                if (($block['type'] ?? null) === 'thinking') {
                    $thinking = $block['thinking'] ?? '';
                    $truncated = strlen($thinking) > 200 ? substr($thinking, 0, 200) . '... (truncated)' : $thinking;
                    $logger->debug('Thinking', ['content' => $truncated]);
                } elseif (($block['type'] ?? null) === 'text') {
                    $logger->debug('Response text', ['text' => $block['text']]);
                } elseif (($block['type'] ?? null) === 'tool_use') {
                    $logger->debug('Tool call', [
                        'tool' => $block['name'],
                        'parameters' => $block['input'],
                    ]);
                }
            } elseif (is_object($block)) {
                // Dynamic properties access on API response
                if (isset($block->type)) {
                    if ($block->type === 'text' && property_exists($block, 'text')) {
                        $logger->debug('Response text', ['text' => $block->text]);
                    } elseif ($block->type === 'tool_use' && property_exists($block, 'name') && property_exists($block, 'input')) {
                        $logger->debug('Tool call', [
                            'tool' => $block->name,
                            'parameters' => $block->input,
                        ]);
                    }
                }
            }
        }

        if (isset($response->stop_reason)) {
            $logger->debug('Stop reason', ['reason' => $response->stop_reason]);
        }

        if (isset($response->usage)) {
            $logger->debug('Token usage', [
                'input' => $response->usage->input_tokens,
                'output' => $response->usage->output_tokens,
            ]);
        }
    }

    /**
     * Manage conversation history to stay within token limits
     *
     * This function keeps the most recent messages and removes older ones
     * to prevent hitting context window limits.
     *
     * @param array $messages Current conversation history
     * @param int $maxMessages Maximum number of message pairs to keep
     * @return array Trimmed message history
     */
    public static function manageConversationHistory(array $messages, int $maxMessages = 10): array
    {
        if (count($messages) <= $maxMessages) {
            return $messages;
        }

        // Keep the most recent messages
        // Always keep user-assistant pairs
        $pairs = [];

        for ($i = 0; $i < count($messages); $i++) {
            $role = $messages[$i]['role'];
            if ($role === 'user') {
                // Start a new pair
                $pairs[] = [$messages[$i]];
            } elseif ($role === 'assistant' && ! empty($pairs)) {
                // Complete the current pair
                $pairs[count($pairs) - 1][] = $messages[$i];
            }
        }

        // Keep the last N pairs
        $keepPairs = array_slice($pairs, -$maxMessages);

        // Flatten back to messages
        $keep = [];
        foreach ($keepPairs as $pair) {
            foreach ($pair as $msg) {
                $keep[] = $msg;
            }
        }

        return $keep;
    }

    /**
     * Calculate approximate token count for messages
     *
     * This is a rough estimation: ~4 characters = 1 token
     * For accurate counts, use the Claude API's countTokens endpoint
     *
     * @param array $messages Message array
     * @return int Approximate token count
     */
    public static function estimateTokens(array $messages): int
    {
        $text = json_encode($messages);

        return (int)ceil(strlen($text) / 4);
    }

    /**
     * Extract text content from a Claude response
     *
     * @param mixed $response Claude API response
     * @return string Combined text from all text blocks
     */
    public static function extractTextContent($response): string
    {
        $text = [];

        if (! isset($response->content)) {
            return '';
        }

        foreach ($response->content as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'text') {
                $text[] = $block['text'];
            } elseif (is_object($block) && isset($block->type) && $block->type === 'text') {
                /** @phpstan-ignore property.notFound */
                $text[] = $block->text;
            }
        }

        return implode("\n", $text);
    }

    /**
     * Create a simple tool definition
     *
     * @param string $name Tool name
     * @param string $description What the tool does
     * @param array $parameters Parameter definitions (property_name => ['type' => ..., 'description' => ...])
     * @param array $required Required parameter names
     * @return array Tool definition for Claude
     */
    public static function createTool(string $name, string $description, array $parameters, array $required = []): array
    {
        return [
            'name' => $name,
            'description' => $description,
            'input_schema' => [
                'type' => 'object',
                'properties' => $parameters,
                'required' => $required,
            ],
        ];
    }

    /**
     * Simple retry wrapper with exponential backoff
     *
     * @param callable $fn Function to retry
     * @param int $maxAttempts Maximum retry attempts
     * @param int $initialDelayMs Initial delay in milliseconds
     * @throws Exception if all retries fail
     * @return mixed Result from function
     */
    public static function retryWithBackoff(callable $fn, int $maxAttempts = 3, int $initialDelayMs = 1000)
    {
        $attempt = 0;
        $delay = $initialDelayMs;
        $lastException = null;

        while ($attempt < $maxAttempts) {
            try {
                return $fn();
            } catch (Exception $e) {
                $lastException = $e;
                $attempt++;

                if ($attempt >= $maxAttempts) {
                    throw $e;
                }

                // Exponential backoff
                usleep($delay * 1000);
                $delay *= 2;
            }
        }

        throw $lastException ?? new Exception('Retry failed with no exception');
    }

    /**
     * Colorize console output (for terminals that support it)
     *
     * @param string $text Text to colorize
     * @param string $color Color name (red, green, yellow, blue, magenta, cyan, white)
     * @return string Colorized text
     */
    public static function colorize(string $text, string $color): string
    {
        $colors = [
            'red' => "\033[31m",
            'green' => "\033[32m",
            'yellow' => "\033[33m",
            'blue' => "\033[34m",
            'magenta' => "\033[35m",
            'cyan' => "\033[36m",
            'white' => "\033[37m",
            'reset' => "\033[0m",
        ];

        $colorCode = $colors[$color] ?? '';
        $resetCode = $colors['reset'];

        return $colorCode . $text . $resetCode;
    }

    /**
     * Pretty print messages for debugging
     *
     * @param array $messages Messages to print
     * @param int $maxContentLength Maximum length of content to show
     * @param LoggerInterface|null $logger Optional logger (defaults to NullLogger)
     */
    public static function printMessages(array $messages, int $maxContentLength = 100, ?LoggerInterface $logger = null): void
    {
        $logger ??= new NullLogger();

        $logger->debug('Conversation History', ['message_count' => count($messages)]);

        foreach ($messages as $i => $message) {
            $role = $message['role'];
            $content = $message['content'];

            if (is_string($content)) {
                $display = strlen($content) > $maxContentLength
                    ? substr($content, 0, $maxContentLength) . '...'
                    : $content;
                $logger->debug("Message {$i}", [
                    'role' => $role,
                    'content' => $display,
                ]);
            } elseif (is_array($content)) {
                $blocks = [];
                foreach ($content as $block) {
                    if (is_array($block)) {
                        $type = $block['type'] ?? 'unknown';
                        if ($type === 'text') {
                            $text = $block['text'] ?? '';
                            $display = strlen($text) > $maxContentLength
                                ? substr($text, 0, $maxContentLength) . '...'
                                : $text;
                            $blocks[] = ['type' => 'text', 'content' => $display];
                        } elseif ($type === 'tool_use') {
                            $blocks[] = ['type' => 'tool_use', 'tool' => $block['name']];
                        } elseif ($type === 'tool_result') {
                            $result = $block['content'] ?? '';
                            $display = strlen($result) > $maxContentLength
                                ? substr($result, 0, $maxContentLength) . '...'
                                : $result;
                            $blocks[] = ['type' => 'tool_result', 'content' => $display];
                        }
                    }
                }
                $logger->debug("Message {$i}", [
                    'role' => $role,
                    'blocks' => $blocks,
                ]);
            }
        }
    }

    /**
     * Check if text contains any of the specified words (case-insensitive)
     *
     * @param string $text Text to search
     * @param array $words Words to look for
     * @return bool True if any word is found
     */
    public static function containsWords(string $text, array $words): bool
    {
        $lowerText = strtolower($text);
        foreach ($words as $word) {
            if (strpos($lowerText, strtolower($word)) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create a simple console logger for debugging
     *
     * This is a convenience method that creates a logger that outputs to stdout.
     * For production use, configure a proper PSR-3 logger with file handlers, etc.
     *
     * @param string $name Logger name
     * @param string $level Minimum log level (debug, info, warning, error)
     * @return LoggerInterface PSR-3 logger instance
     */
    public static function createConsoleLogger(string $name = 'agent', string $level = 'debug'): LoggerInterface
    {
        // Use a simple console logger
        return new class ($name, $level) implements LoggerInterface {
            private string $name;
            private array $levelMap = [
                'debug' => 0,
                'info' => 1,
                'notice' => 2,
                'warning' => 3,
                'error' => 4,
                'critical' => 5,
                'alert' => 6,
                'emergency' => 7,
            ];
            private int $minLevel;

            public function __construct(string $name, string $level)
            {
                $this->name = $name;
                $this->minLevel = $this->levelMap[strtolower($level)] ?? 0;
            }

            public function emergency($message, array $context = []): void
            {
                $this->log('emergency', $message, $context);
            }

            public function alert($message, array $context = []): void
            {
                $this->log('alert', $message, $context);
            }

            public function critical($message, array $context = []): void
            {
                $this->log('critical', $message, $context);
            }

            public function error($message, array $context = []): void
            {
                $this->log('error', $message, $context);
            }

            public function warning($message, array $context = []): void
            {
                $this->log('warning', $message, $context);
            }

            public function notice($message, array $context = []): void
            {
                $this->log('notice', $message, $context);
            }

            public function info($message, array $context = []): void
            {
                $this->log('info', $message, $context);
            }

            public function debug($message, array $context = []): void
            {
                $this->log('debug', $message, $context);
            }

            public function log($level, $message, array $context = []): void
            {
                $levelNum = $this->levelMap[strtolower((string)$level)] ?? 0;

                if ($levelNum < $this->minLevel) {
                    return;
                }

                $timestamp = date('Y-m-d H:i:s');
                $levelStr = strtoupper((string)$level);
                $contextStr = empty($context) ? '' : ' ' . json_encode($context);

                echo "[{$timestamp}] {$this->name}.{$levelStr}: {$message}{$contextStr}\n";
            }
        };
    }
}
