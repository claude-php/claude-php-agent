<?php

declare(strict_types=1);

namespace ClaudeAgents\Observability\Exporters;

/**
 * Export metrics in Prometheus format.
 *
 * Can write to file or expose via HTTP endpoint.
 */
class PrometheusExporter implements ExporterInterface
{
    public function __construct(
        private readonly string $outputPath,
        private readonly string $prefix = 'claude_agent',
    ) {
    }

    public function export(array $data): bool
    {
        $output = [];

        // Add timestamp
        $timestamp = time() * 1000;

        // Export counters
        if (isset($data['counters'])) {
            foreach ($data['counters'] as $name => $value) {
                $metricName = $this->formatMetricName($name);
                $output[] = "# TYPE {$metricName} counter";
                $output[] = "{$metricName} {$value} {$timestamp}";
            }
        }

        // Export gauges
        if (isset($data['gauges'])) {
            foreach ($data['gauges'] as $name => $value) {
                $metricName = $this->formatMetricName($name);
                $output[] = "# TYPE {$metricName} gauge";
                $output[] = "{$metricName} {$value} {$timestamp}";
            }
        }

        // Export duration metrics
        if (isset($data['duration'])) {
            $this->exportDurationMetrics($data['duration'], $output, $timestamp);
        }

        // Export rate metrics
        if (isset($data['rate'])) {
            $this->exportRateMetrics($data['rate'], $output, $timestamp);
        }

        // Export token metrics
        if (isset($data['tokens'])) {
            $this->exportTokenMetrics($data['tokens'], $output, $timestamp);
        }

        $content = implode("\n", $output) . "\n";

        try {
            $dir = dirname($this->outputPath);
            if (! is_dir($dir)) {
                mkdir($dir, 0o755, true);
            }

            file_put_contents($this->outputPath, $content);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function exportDurationMetrics(array $duration, array &$output, int $timestamp): void
    {
        $metricName = $this->prefix . '_duration_ms';

        // Export histogram
        if (isset($duration['histogram'])) {
            $output[] = "# TYPE {$metricName} histogram";

            $cumulative = 0;
            foreach ($duration['histogram']['buckets'] as $bucket => $count) {
                $cumulative += $count;
                $le = $bucket === '+Inf' ? '+Inf' : rtrim($bucket, 'ms');
                $output[] = "{$metricName}_bucket{le=\"{$le}\"} {$cumulative} {$timestamp}";
            }

            $output[] = "{$metricName}_sum {$duration['sum']} {$timestamp}";
            $output[] = "{$metricName}_count {$duration['count']} {$timestamp}";
        }

        // Export percentiles as gauges
        if (isset($duration['percentiles'])) {
            foreach ($duration['percentiles'] as $percentile => $value) {
                $pName = $this->prefix . '_duration_ms_' . $percentile;
                $output[] = "# TYPE {$pName} gauge";
                $output[] = "{$pName} {$value} {$timestamp}";
            }
        }
    }

    private function exportRateMetrics(array $rate, array &$output, int $timestamp): void
    {
        if (isset($rate['requests_per_second'])) {
            $metricName = $this->prefix . '_requests_per_second';
            $output[] = "# TYPE {$metricName} gauge";
            $output[] = "{$metricName} {$rate['requests_per_second']} {$timestamp}";
        }

        if (isset($rate['success_rate_percent'])) {
            $metricName = $this->prefix . '_success_rate';
            $value = $rate['success_rate_percent'] / 100;
            $output[] = "# TYPE {$metricName} gauge";
            $output[] = "{$metricName} {$value} {$timestamp}";
        }
    }

    private function exportTokenMetrics(array $tokens, array &$output, int $timestamp): void
    {
        $metricName = $this->prefix . '_tokens_total';
        $output[] = "# TYPE {$metricName} counter";
        $output[] = "{$metricName} {$tokens['total']} {$timestamp}";

        if (isset($tokens['mean'])) {
            $avgName = $this->prefix . '_tokens_avg';
            $output[] = "# TYPE {$avgName} gauge";
            $output[] = "{$avgName} {$tokens['mean']} {$timestamp}";
        }
    }

    private function formatMetricName(string $name): string
    {
        return $this->prefix . '_' . str_replace(['.', '-', ' '], '_', strtolower($name));
    }

    public function getName(): string
    {
        return 'prometheus';
    }

    public function getOutputPath(): string
    {
        return $this->outputPath;
    }
}
