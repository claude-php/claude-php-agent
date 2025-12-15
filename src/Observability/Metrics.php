<?php

declare(strict_types=1);

namespace ClaudeAgents\Observability;

/**
 * Collects metrics for monitoring and analysis.
 */
class Metrics
{
    private int $totalRequests = 0;
    private int $successfulRequests = 0;
    private int $failedRequests = 0;
    private int $totalTokensInput = 0;
    private int $totalTokensOutput = 0;
    private float $totalDuration = 0; // milliseconds

    /**
     * @var array<string, int> Error counts by type
     */
    private array $errorCounts = [];

    /**
     * Record a request.
     *
     * @param bool $success Whether the request succeeded
     * @param int $tokensInput Input tokens used
     * @param int $tokensOutput Output tokens used
     * @param float $duration Request duration in milliseconds
     * @param string|null $error Error message if failed
     */
    public function recordRequest(
        bool $success,
        int $tokensInput = 0,
        int $tokensOutput = 0,
        float $duration = 0,
        ?string $error = null,
    ): void {
        $this->totalRequests++;

        if ($success) {
            $this->successfulRequests++;
        } else {
            $this->failedRequests++;
            if ($error !== null) {
                $errorType = explode(':', $error)[0];
                $this->errorCounts[$errorType] = ($this->errorCounts[$errorType] ?? 0) + 1;
            }
        }

        $this->totalTokensInput += $tokensInput;
        $this->totalTokensOutput += $tokensOutput;
        $this->totalDuration += $duration;
    }

    /**
     * Get metrics summary.
     *
     * @return array<string, mixed>
     */
    public function getSummary(): array
    {
        return [
            'total_requests' => $this->totalRequests,
            'successful_requests' => $this->successfulRequests,
            'failed_requests' => $this->failedRequests,
            'success_rate' => $this->totalRequests > 0 ? $this->successfulRequests / $this->totalRequests : 0,
            'total_tokens' => [
                'input' => $this->totalTokensInput,
                'output' => $this->totalTokensOutput,
                'total' => $this->totalTokensInput + $this->totalTokensOutput,
            ],
            'total_duration_ms' => $this->totalDuration,
            'average_duration_ms' => $this->totalRequests > 0 ? $this->totalDuration / $this->totalRequests : 0,
            'error_counts' => $this->errorCounts,
        ];
    }

    /**
     * Reset all metrics.
     */
    public function reset(): void
    {
        $this->totalRequests = 0;
        $this->successfulRequests = 0;
        $this->failedRequests = 0;
        $this->totalTokensInput = 0;
        $this->totalTokensOutput = 0;
        $this->totalDuration = 0;
        $this->errorCounts = [];
    }
}
