<?php

declare(strict_types=1);

namespace ClaudeAgents\Helpers;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Structured logging for AI agents
 *
 * Provides detailed logging of agent iterations, tool usage, token consumption,
 * and performance metrics.
 */
class AgentLogger
{
    private LoggerInterface $logger;
    private array $sessionMetrics = [];
    private float $sessionStartTime;
    private string $sessionId;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
        $this->sessionStartTime = microtime(true);
        $this->sessionId = uniqid('agent_', true);

        $this->sessionMetrics = [
            'total_iterations' => 0,
            'total_tool_calls' => 0,
            'total_input_tokens' => 0,
            'total_output_tokens' => 0,
            'total_errors' => 0,
            'tools_used' => [],
        ];
    }

    /**
     * Log an agent iteration
     *
     * @param int $iteration Iteration number
     * @param mixed $response Claude API response
     * @param string|null $agentName Optional agent identifier
     */
    public function logIteration(int $iteration, $response, ?string $agentName = null): void
    {
        $this->sessionMetrics['total_iterations']++;

        $toolsUsed = $this->extractToolsUsed($response);
        $this->sessionMetrics['total_tool_calls'] += count($toolsUsed);

        foreach ($toolsUsed as $tool) {
            if (! isset($this->sessionMetrics['tools_used'][$tool])) {
                $this->sessionMetrics['tools_used'][$tool] = 0;
            }
            $this->sessionMetrics['tools_used'][$tool]++;
        }

        if (isset($response->usage)) {
            $this->sessionMetrics['total_input_tokens'] += $response->usage->input_tokens ?? 0;
            $this->sessionMetrics['total_output_tokens'] += $response->usage->output_tokens ?? 0;
        }

        $logData = [
            'session_id' => $this->sessionId,
            'agent' => $agentName,
            'iteration' => $iteration,
            'stop_reason' => $response->stop_reason ?? 'unknown',
            'tools_used' => $toolsUsed,
            'tokens' => [
                'input' => $response->usage->input_tokens ?? 0,
                'output' => $response->usage->output_tokens ?? 0,
                'total' => ($response->usage->input_tokens ?? 0) + ($response->usage->output_tokens ?? 0),
            ],
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        $this->logger->info("Agent iteration {$iteration}", $logData);
    }

    /**
     * Log a tool execution
     *
     * @param string $toolName Tool name
     * @param array $input Tool input
     * @param mixed $result Tool result
     * @param bool $success Whether execution succeeded
     * @param float|null $duration Execution time in seconds
     */
    public function logToolExecution(
        string $toolName,
        array $input,
        $result,
        bool $success = true,
        ?float $duration = null
    ): void {
        if (! $success) {
            $this->sessionMetrics['total_errors']++;
        }

        $logData = [
            'session_id' => $this->sessionId,
            'tool' => $toolName,
            'success' => $success,
            'input_size' => strlen(json_encode($input)),
            'result_size' => is_string($result) ? strlen($result) : strlen(json_encode($result)),
            'duration' => $duration,
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        $level = $success ? 'debug' : 'error';
        $message = $success
            ? "Tool '{$toolName}' executed successfully"
            : "Tool '{$toolName}' failed";

        $this->logger->$level($message, $logData);
    }

    /**
     * Log an error
     *
     * @param string $message Error message
     * @param array $context Additional context
     */
    public function logError(string $message, array $context = []): void
    {
        $this->sessionMetrics['total_errors']++;

        $logData = array_merge([
            'session_id' => $this->sessionId,
            'timestamp' => date('Y-m-d H:i:s'),
        ], $context);

        $this->logger->error($message, $logData);
    }

    /**
     * Log session summary
     *
     * @param bool $success Whether the session completed successfully
     * @param string|null $agentName Optional agent identifier
     */
    public function logSessionSummary(bool $success = true, ?string $agentName = null): void
    {
        $duration = microtime(true) - $this->sessionStartTime;
        $totalTokens = $this->sessionMetrics['total_input_tokens'] + $this->sessionMetrics['total_output_tokens'];

        // Estimate cost (approximate, based on Claude 3.5 Sonnet pricing)
        $estimatedCost = $this->estimateCost(
            $this->sessionMetrics['total_input_tokens'],
            $this->sessionMetrics['total_output_tokens']
        );

        $logData = [
            'session_id' => $this->sessionId,
            'agent' => $agentName,
            'success' => $success,
            'duration' => round($duration, 2),
            'iterations' => $this->sessionMetrics['total_iterations'],
            'tool_calls' => $this->sessionMetrics['total_tool_calls'],
            'tokens' => [
                'input' => $this->sessionMetrics['total_input_tokens'],
                'output' => $this->sessionMetrics['total_output_tokens'],
                'total' => $totalTokens,
            ],
            'estimated_cost_usd' => $estimatedCost,
            'errors' => $this->sessionMetrics['total_errors'],
            'tools_used' => $this->sessionMetrics['tools_used'],
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        $this->logger->info('Agent session completed', $logData);
    }

    /**
     * Get current session metrics
     *
     * @return array Metrics array
     */
    public function getMetrics(): array
    {
        $duration = microtime(true) - $this->sessionStartTime;

        return array_merge($this->sessionMetrics, [
            'session_id' => $this->sessionId,
            'duration' => round($duration, 2),
            'estimated_cost_usd' => $this->estimateCost(
                $this->sessionMetrics['total_input_tokens'],
                $this->sessionMetrics['total_output_tokens']
            ),
        ]);
    }

    /**
     * Reset metrics for a new session
     */
    public function reset(): void
    {
        $this->sessionStartTime = microtime(true);
        $this->sessionId = uniqid('agent_', true);

        $this->sessionMetrics = [
            'total_iterations' => 0,
            'total_tool_calls' => 0,
            'total_input_tokens' => 0,
            'total_output_tokens' => 0,
            'total_errors' => 0,
            'tools_used' => [],
        ];
    }

    /**
     * Extract tool names from a Claude response
     *
     * @param mixed $response Claude API response
     * @return array Array of tool names
     */
    private function extractToolsUsed($response): array
    {
        $tools = [];

        if (! isset($response->content)) {
            return $tools;
        }

        foreach ($response->content as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'tool_use') {
                $tools[] = $block['name'];
            } elseif (is_object($block) && isset($block->type) && $block->type === 'tool_use') {
                /** @phpstan-ignore property.notFound */
                $tools[] = $block->name;
            }
        }

        return $tools;
    }

    /**
     * Estimate cost based on token usage
     *
     * Uses Claude 3.5 Sonnet pricing as default:
     * - $3 per 1M input tokens
     * - $15 per 1M output tokens
     *
     * @param int $inputTokens Input tokens used
     * @param int $outputTokens Output tokens used
     * @return float Estimated cost in USD
     */
    private function estimateCost(int $inputTokens, int $outputTokens): float
    {
        $inputCostPerMillion = 3.0;
        $outputCostPerMillion = 15.0;

        $inputCost = ($inputTokens / 1000000) * $inputCostPerMillion;
        $outputCost = ($outputTokens / 1000000) * $outputCostPerMillion;

        return round($inputCost + $outputCost, 6);
    }

    /**
     * Create a file logger that appends JSON logs
     *
     * @param string $filepath Path to log file
     * @return callable Logger function
     */
    public static function createFileLogger(string $filepath): callable
    {
        return function (string $level, string $message, array $context = []) use ($filepath) {
            $logEntry = [
                'timestamp' => date('Y-m-d H:i:s'),
                'level' => strtoupper($level),
                'message' => $message,
                'context' => $context,
            ];

            file_put_contents(
                $filepath,
                json_encode($logEntry) . "\n",
                FILE_APPEND
            );
        };
    }
}
