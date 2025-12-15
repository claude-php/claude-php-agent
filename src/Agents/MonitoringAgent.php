<?php

declare(strict_types=1);

namespace ClaudeAgents\Agents;

use ClaudeAgents\AgentResult;
use ClaudeAgents\Contracts\AgentInterface;
use ClaudeAgents\Contracts\MonitorableInterface;
use ClaudeAgents\Monitoring\Alert;
use ClaudeAgents\Monitoring\Metric;
use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Monitoring Agent - Watches data streams for anomalies and changes.
 *
 * Monitors metrics from various sources, detects anomalies using LLM analysis,
 * and generates alerts based on thresholds and patterns.
 */
class MonitoringAgent implements AgentInterface
{
    private ClaudePhp $client;
    private string $name;
    private int $checkInterval;
    private array $thresholds;
    private array $history = [];
    private array $alerts = [];
    private LoggerInterface $logger;
    private bool $isRunning = false;

    /**
     * @param ClaudePhp $client Claude API client
     * @param array<string, mixed> $options Configuration:
     *   - name: Agent name
     *   - check_interval: Polling interval in seconds (default: 60)
     *   - thresholds: Metric name => threshold value
     *   - max_history: Maximum history entries to keep (default: 1000)
     *   - logger: PSR-3 logger
     */
    public function __construct(ClaudePhp $client, array $options = [])
    {
        $this->client = $client;
        $this->name = $options['name'] ?? 'monitoring_agent';
        $this->checkInterval = $options['check_interval'] ?? 60;
        $this->thresholds = $options['thresholds'] ?? [];
        $this->logger = $options['logger'] ?? new NullLogger();
    }

    public function run(string $task): AgentResult
    {
        // Single check execution
        $this->logger->info("Monitoring agent: {$task}");

        try {
            $metrics = $this->parseMetricsFromTask($task);
            $alerts = $this->analyzeMetrics($metrics);

            return AgentResult::success(
                answer: $this->formatAnalysis($metrics, $alerts),
                messages: [],
                iterations: 1,
                metadata: [
                    'metrics_analyzed' => count($metrics),
                    'alerts_generated' => count($alerts),
                    'alerts' => array_map(fn ($a) => $a->toArray(), $alerts),
                ],
            );
        } catch (\Throwable $e) {
            $this->logger->error("Monitoring failed: {$e->getMessage()}");

            return AgentResult::failure(error: $e->getMessage());
        }
    }

    /**
     * Watch a monitorable source continuously.
     *
     * @param MonitorableInterface $source Data source to monitor
     * @param callable $onAlert Callback when alert is generated: fn(Alert): void
     */
    public function watch(MonitorableInterface $source, callable $onAlert): void
    {
        $this->logger->info("Starting monitoring of: {$source->getName()}");
        $this->isRunning = true;

        while ($this->isRunning) {
            try {
                $metrics = $source->getMetrics();
                $this->recordMetrics($metrics);

                $alerts = $this->analyzeMetrics($metrics);

                foreach ($alerts as $alert) {
                    $this->logger->warning("Alert generated: {$alert->getTitle()}");
                    $onAlert($alert);
                }

                sleep($this->checkInterval);
            } catch (\Throwable $e) {
                $this->logger->error("Monitoring error: {$e->getMessage()}");
                sleep($this->checkInterval);
            }
        }
    }

    /**
     * Stop monitoring.
     */
    public function stop(): void
    {
        $this->isRunning = false;
        $this->logger->info('Monitoring stopped');
    }

    /**
     * Analyze metrics and generate alerts.
     *
     * @param array<Metric> $metrics
     * @return array<Alert>
     */
    private function analyzeMetrics(array $metrics): array
    {
        $alerts = [];

        foreach ($metrics as $metric) {
            // Threshold-based alerting
            if (isset($this->thresholds[$metric->getName()])) {
                $threshold = $this->thresholds[$metric->getName()];

                if ($metric->exceedsThreshold($threshold)) {
                    $alerts[] = new Alert(
                        title: "{$metric->getName()} exceeds threshold",
                        message: "Metric '{$metric->getName()}' value {$metric->getValue()} exceeds threshold {$threshold}",
                        severity: Alert::SEVERITY_WARNING,
                        metric: $metric,
                    );
                }
            }

            // LLM-based anomaly detection for complex patterns
            if ($this->hasHistoricalData($metric->getName())) {
                $anomaly = $this->detectAnomalyWithLLM($metric);
                if ($anomaly) {
                    $alerts[] = $anomaly;
                }
            }
        }

        return $alerts;
    }

    /**
     * Detect anomalies using LLM analysis.
     */
    private function detectAnomalyWithLLM(Metric $metric): ?Alert
    {
        $historical = $this->getHistoricalData($metric->getName(), 10);

        if (count($historical) < 5) {
            return null; // Need more data
        }

        $historicalValues = array_map(fn ($m) => $m->getValue(), $historical);
        $prompt = $this->buildAnomalyDetectionPrompt($metric, $historicalValues);

        try {
            $response = $this->client->messages()->create([
                'model' => 'claude-sonnet-4-5',
                'max_tokens' => 512,
                'system' => 'You are an anomaly detection system. Analyze metrics and identify unusual patterns.',
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

            $analysis = $this->extractTextContent($response->content ?? []);

            if ($this->indicatesAnomaly($analysis)) {
                return new Alert(
                    title: "Anomaly detected in {$metric->getName()}",
                    message: $analysis,
                    severity: Alert::SEVERITY_WARNING,
                    metric: $metric,
                    context: ['historical_values' => $historicalValues],
                );
            }
        } catch (\Throwable $e) {
            $this->logger->warning("LLM anomaly detection failed: {$e->getMessage()}");
        }

        return null;
    }

    /**
     * Build prompt for anomaly detection.
     */
    private function buildAnomalyDetectionPrompt(Metric $metric, array $historical): string
    {
        $historicalStr = implode(', ', $historical);

        return <<<PROMPT
            Analyze this metric for anomalies:

            Metric: {$metric->getName()}
            Current Value: {$metric->getValue()}
            Historical Values (recent): [{$historicalStr}]

            Is the current value anomalous compared to the historical pattern?
            Consider: sudden spikes, drops, or unusual deviations from the trend.

            Respond with:
            - "ANOMALY" if detected, followed by explanation
            - "NORMAL" if no anomaly detected
            PROMPT;
    }

    /**
     * Check if LLM response indicates an anomaly.
     */
    private function indicatesAnomaly(string $analysis): bool
    {
        return stripos($analysis, 'ANOMALY') !== false;
    }

    /**
     * Parse metrics from task description.
     *
     * @return array<Metric>
     */
    private function parseMetricsFromTask(string $task): array
    {
        // Extract metrics from formatted text
        // Expected format: "metric_name: value"
        $metrics = [];
        $lines = explode("\n", $task);

        foreach ($lines as $line) {
            if (preg_match('/^(.+?):\s*(.+)$/i', trim($line), $matches)) {
                $name = trim($matches[1]);
                $value = trim($matches[2]);

                // Try to convert to number
                if (is_numeric($value)) {
                    $value = floatval($value);
                }

                $metrics[] = new Metric($name, $value);
            }
        }

        return $metrics;
    }

    /**
     * Record metrics to history.
     *
     * @param array<Metric> $metrics
     */
    private function recordMetrics(array $metrics): void
    {
        foreach ($metrics as $metric) {
            if (! isset($this->history[$metric->getName()])) {
                $this->history[$metric->getName()] = [];
            }

            $this->history[$metric->getName()][] = $metric;

            // Keep only recent history (last 1000 entries)
            if (count($this->history[$metric->getName()]) > 1000) {
                array_shift($this->history[$metric->getName()]);
            }
        }
    }

    /**
     * Check if we have historical data for a metric.
     */
    private function hasHistoricalData(string $metricName): bool
    {
        return isset($this->history[$metricName]) && count($this->history[$metricName]) > 0;
    }

    /**
     * Get historical data for a metric.
     *
     * @return array<Metric>
     */
    private function getHistoricalData(string $metricName, int $limit = 10): array
    {
        if (! isset($this->history[$metricName])) {
            return [];
        }

        return array_slice($this->history[$metricName], -$limit);
    }

    /**
     * Format analysis results.
     *
     * @param array<Metric> $metrics
     * @param array<Alert> $alerts
     */
    private function formatAnalysis(array $metrics, array $alerts): string
    {
        $output = "Monitoring Analysis\n";
        $output .= "==================\n\n";

        $output .= 'Metrics Analyzed: ' . count($metrics) . "\n";

        foreach ($metrics as $metric) {
            $output .= "  - {$metric->getName()}: {$metric->getValue()}\n";
        }

        $output .= "\nAlerts Generated: " . count($alerts) . "\n";

        foreach ($alerts as $alert) {
            $output .= "  [{$alert->getSeverity()}] {$alert->getTitle()}\n";
            $output .= "    {$alert->getMessage()}\n";
        }

        return $output;
    }

    /**
     * Extract text content from response blocks.
     *
     * @param array<mixed> $content
     */
    private function extractTextContent(array $content): string
    {
        $texts = [];

        foreach ($content as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'text') {
                $texts[] = $block['text'] ?? '';
            }
        }

        return implode("\n", $texts);
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get all generated alerts.
     *
     * @return array<Alert>
     */
    public function getAlerts(): array
    {
        return $this->alerts;
    }
}
