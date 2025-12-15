<?php

declare(strict_types=1);

namespace ClaudeAgents\Observability;

/**
 * Advanced metrics aggregation with histograms, percentiles, and time windows.
 *
 * Provides statistical analysis of metrics including:
 * - Histogram buckets
 * - Percentile calculations (p50, p95, p99)
 * - Rate calculations (requests per second)
 * - Moving averages
 */
class MetricsAggregator
{
    /**
     * @var array<float> Duration samples for histogram
     */
    private array $durations = [];

    /**
     * @var array<int> Token count samples
     */
    private array $tokenCounts = [];

    /**
     * @var array<array{timestamp: float, success: bool}> Request history for rate calculation
     */
    private array $requestHistory = [];

    /**
     * @var array<string, int> Counter values
     */
    private array $counters = [];

    /**
     * @var array<string, float> Gauge values
     */
    private array $gauges = [];

    /**
     * @var int Time window in seconds for rate calculations
     */
    private int $timeWindow;

    public function __construct(int $timeWindow = 60)
    {
        $this->timeWindow = $timeWindow;
    }

    /**
     * Record a duration sample.
     */
    public function recordDuration(float $durationMs): void
    {
        $this->durations[] = $durationMs;
    }

    /**
     * Record token count.
     */
    public function recordTokens(int $tokens): void
    {
        $this->tokenCounts[] = $tokens;
    }

    /**
     * Record a request.
     */
    public function recordRequest(bool $success): void
    {
        $this->requestHistory[] = [
            'timestamp' => microtime(true),
            'success' => $success,
        ];

        // Clean old entries
        $this->cleanOldRequests();
    }

    /**
     * Increment a counter.
     */
    public function incrementCounter(string $name, int $value = 1): void
    {
        $this->counters[$name] = ($this->counters[$name] ?? 0) + $value;
    }

    /**
     * Set a gauge value.
     */
    public function setGauge(string $name, float $value): void
    {
        $this->gauges[$name] = $value;
    }

    /**
     * Get histogram data for durations.
     *
     * @param array<float> $buckets Bucket boundaries in milliseconds
     * @return array<string, mixed>
     */
    public function getDurationHistogram(array $buckets = [10, 50, 100, 250, 500, 1000, 2500, 5000]): array
    {
        sort($buckets);

        $histogram = [];
        foreach ($buckets as $bucket) {
            $histogram["{$bucket}ms"] = 0;
        }
        $histogram['+Inf'] = 0;

        foreach ($this->durations as $duration) {
            $placed = false;
            foreach ($buckets as $bucket) {
                if ($duration <= $bucket) {
                    $histogram["{$bucket}ms"]++;
                    $placed = true;

                    break;
                }
            }
            if (! $placed) {
                $histogram['+Inf']++;
            }
        }

        return [
            'buckets' => $histogram,
            'count' => count($this->durations),
            'sum' => array_sum($this->durations),
        ];
    }

    /**
     * Calculate percentiles for durations.
     *
     * @param array<int> $percentiles Percentiles to calculate (e.g., [50, 95, 99])
     * @return array<string, float>
     */
    public function getDurationPercentiles(array $percentiles = [50, 95, 99]): array
    {
        if (empty($this->durations)) {
            return array_fill_keys(array_map(fn ($p) => "p{$p}", $percentiles), 0.0);
        }

        $sorted = $this->durations;
        sort($sorted);
        $count = count($sorted);

        $result = [];
        foreach ($percentiles as $percentile) {
            $index = (int)ceil(($percentile / 100) * $count) - 1;
            $index = max(0, min($index, $count - 1));
            $result["p{$percentile}"] = $sorted[$index];
        }

        return $result;
    }

    /**
     * Get statistics for durations.
     *
     * @return array<string, float>
     */
    public function getDurationStats(): array
    {
        if (empty($this->durations)) {
            return [
                'count' => 0,
                'sum' => 0.0,
                'min' => 0.0,
                'max' => 0.0,
                'mean' => 0.0,
                'median' => 0.0,
                'stddev' => 0.0,
            ];
        }

        $count = count($this->durations);
        $sum = array_sum($this->durations);
        $mean = $sum / $count;

        // Calculate standard deviation
        $variance = 0;
        foreach ($this->durations as $duration) {
            $variance += pow($duration - $mean, 2);
        }
        $stddev = sqrt($variance / $count);

        $sorted = $this->durations;
        sort($sorted);
        $median = $sorted[(int)floor($count / 2)];

        return [
            'count' => $count,
            'sum' => $sum,
            'min' => min($this->durations),
            'max' => max($this->durations),
            'mean' => $mean,
            'median' => $median,
            'stddev' => $stddev,
        ];
    }

    /**
     * Get token statistics.
     *
     * @return array<string, mixed>
     */
    public function getTokenStats(): array
    {
        if (empty($this->tokenCounts)) {
            return [
                'count' => 0,
                'total' => 0,
                'min' => 0,
                'max' => 0,
                'mean' => 0.0,
            ];
        }

        return [
            'count' => count($this->tokenCounts),
            'total' => array_sum($this->tokenCounts),
            'min' => min($this->tokenCounts),
            'max' => max($this->tokenCounts),
            'mean' => array_sum($this->tokenCounts) / count($this->tokenCounts),
        ];
    }

    /**
     * Get request rate (requests per second).
     */
    public function getRequestRate(): float
    {
        $this->cleanOldRequests();

        if (empty($this->requestHistory)) {
            return 0.0;
        }

        $timespan = microtime(true) - $this->requestHistory[0]['timestamp'];

        return $timespan > 0 ? count($this->requestHistory) / $timespan : 0.0;
    }

    /**
     * Get success rate as percentage.
     */
    public function getSuccessRate(): float
    {
        $this->cleanOldRequests();

        if (empty($this->requestHistory)) {
            return 0.0;
        }

        $successful = count(array_filter($this->requestHistory, fn ($r) => $r['success']));

        return ($successful / count($this->requestHistory)) * 100;
    }

    /**
     * Get all counters.
     *
     * @return array<string, int>
     */
    public function getCounters(): array
    {
        return $this->counters;
    }

    /**
     * Get all gauges.
     *
     * @return array<string, float>
     */
    public function getGauges(): array
    {
        return $this->gauges;
    }

    /**
     * Get counter value.
     */
    public function getCounter(string $name): int
    {
        return $this->counters[$name] ?? 0;
    }

    /**
     * Get gauge value.
     */
    public function getGauge(string $name): float
    {
        return $this->gauges[$name] ?? 0.0;
    }

    /**
     * Get comprehensive summary.
     *
     * @return array<string, mixed>
     */
    public function getSummary(): array
    {
        return [
            'duration' => array_merge(
                $this->getDurationStats(),
                ['percentiles' => $this->getDurationPercentiles()],
                ['histogram' => $this->getDurationHistogram()]
            ),
            'tokens' => $this->getTokenStats(),
            'rate' => [
                'requests_per_second' => $this->getRequestRate(),
                'success_rate_percent' => $this->getSuccessRate(),
            ],
            'counters' => $this->counters,
            'gauges' => $this->gauges,
        ];
    }

    /**
     * Export to Prometheus format.
     *
     * @return string
     */
    public function toPrometheus(string $prefix = 'claude_agent'): string
    {
        $output = [];

        // Counters
        foreach ($this->counters as $name => $value) {
            $metricName = $prefix . '_' . str_replace(['.', '-'], '_', $name);
            $output[] = "# TYPE {$metricName} counter";
            $output[] = "{$metricName} {$value}";
        }

        // Gauges
        foreach ($this->gauges as $name => $value) {
            $metricName = $prefix . '_' . str_replace(['.', '-'], '_', $name);
            $output[] = "# TYPE {$metricName} gauge";
            $output[] = "{$metricName} {$value}";
        }

        // Duration histogram
        if (! empty($this->durations)) {
            $histogram = $this->getDurationHistogram();
            $metricName = $prefix . '_duration_ms';
            $output[] = "# TYPE {$metricName} histogram";

            $cumulative = 0;
            foreach ($histogram['buckets'] as $bucket => $count) {
                $cumulative += $count;
                $le = $bucket === '+Inf' ? '+Inf' : rtrim($bucket, 'ms');
                $output[] = "{$metricName}_bucket{le=\"{$le}\"} {$cumulative}";
            }

            $output[] = "{$metricName}_sum {$histogram['sum']}";
            $output[] = "{$metricName}_count {$histogram['count']}";
        }

        return implode("\n", $output) . "\n";
    }

    /**
     * Reset all metrics.
     */
    public function reset(): void
    {
        $this->durations = [];
        $this->tokenCounts = [];
        $this->requestHistory = [];
        $this->counters = [];
        $this->gauges = [];
    }

    /**
     * Clean old requests outside time window.
     */
    private function cleanOldRequests(): void
    {
        $cutoff = microtime(true) - $this->timeWindow;
        $this->requestHistory = array_filter(
            $this->requestHistory,
            fn ($r) => $r['timestamp'] >= $cutoff
        );
    }
}
