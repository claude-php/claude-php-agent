<?php

declare(strict_types=1);

namespace ClaudeAgents\Support;

/**
 * Tracks token usage across agent executions.
 */
class TokenTracker
{
    private int $inputTokens = 0;
    private int $outputTokens = 0;
    private int $requestCount = 0;

    /**
     * @var array<array{input: int, output: int, timestamp: int}>
     */
    private array $history = [];

    /**
     * Record token usage from a request.
     */
    public function record(int $inputTokens, int $outputTokens): void
    {
        $this->inputTokens += $inputTokens;
        $this->outputTokens += $outputTokens;
        $this->requestCount++;

        $this->history[] = [
            'input' => $inputTokens,
            'output' => $outputTokens,
            'timestamp' => time(),
        ];
    }

    /**
     * Get total input tokens.
     */
    public function getInputTokens(): int
    {
        return $this->inputTokens;
    }

    /**
     * Get total output tokens.
     */
    public function getOutputTokens(): int
    {
        return $this->outputTokens;
    }

    /**
     * Get total tokens (input + output).
     */
    public function getTotalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }

    /**
     * Get number of requests made.
     */
    public function getRequestCount(): int
    {
        return $this->requestCount;
    }

    /**
     * Get average tokens per request.
     */
    public function getAverageTokensPerRequest(): float
    {
        if ($this->requestCount === 0) {
            return 0.0;
        }

        return $this->getTotalTokens() / $this->requestCount;
    }

    /**
     * Get usage history.
     *
     * @return array<array{input: int, output: int, timestamp: int}>
     */
    public function getHistory(): array
    {
        return $this->history;
    }

    /**
     * Reset the tracker.
     */
    public function reset(): void
    {
        $this->inputTokens = 0;
        $this->outputTokens = 0;
        $this->requestCount = 0;
        $this->history = [];
    }

    /**
     * Get a summary of token usage.
     *
     * @return array<string, mixed>
     */
    public function getSummary(): array
    {
        return [
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'total_tokens' => $this->getTotalTokens(),
            'request_count' => $this->requestCount,
            'average_per_request' => round($this->getAverageTokensPerRequest(), 2),
        ];
    }

    /**
     * Estimate cost based on token usage.
     *
     * @param float $inputPricePerMillion Price per million input tokens
     * @param float $outputPricePerMillion Price per million output tokens
     */
    public function estimateCost(
        float $inputPricePerMillion = 3.0,
        float $outputPricePerMillion = 15.0,
    ): float {
        $inputCost = ($this->inputTokens / 1_000_000) * $inputPricePerMillion;
        $outputCost = ($this->outputTokens / 1_000_000) * $outputPricePerMillion;

        return $inputCost + $outputCost;
    }
}
