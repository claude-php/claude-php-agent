<?php

declare(strict_types=1);

namespace ClaudeAgents\Services\Telemetry;

use ClaudeAgents\Observability\Metrics;
use ClaudeAgents\Services\ServiceInterface;
use ClaudeAgents\Services\Settings\SettingsService;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Telemetry service for OpenTelemetry integration.
 *
 * Collects and exports metrics, traces, and logs.
 */
class TelemetryService implements ServiceInterface
{
    private bool $ready = false;
    private Metrics $metrics;
    private LoggerInterface $logger;

    /**
     * @var array<string, int> Counter metrics
     */
    private array $counters = [];

    /**
     * @var array<string, float> Gauge metrics
     */
    private array $gauges = [];

    /**
     * @var array<string, array<float>> Histogram metrics
     */
    private array $histograms = [];

    /**
     * @param SettingsService $settings Settings service
     */
    public function __construct(
        private SettingsService $settings
    ) {
        $this->metrics = new Metrics();
        $this->logger = new NullLogger();
    }

    public function getName(): string
    {
        return 'telemetry';
    }

    public function initialize(): void
    {
        if ($this->ready) {
            return;
        }

        // Check if telemetry is enabled
        if (! $this->settings->get('telemetry.enabled', false)) {
            $this->ready = true;

            return;
        }

        $this->ready = true;
    }

    public function teardown(): void
    {
        // Flush any pending metrics
        $this->flush();

        $this->ready = false;
    }

    public function isReady(): bool
    {
        return $this->ready;
    }

    public function getSchema(): array
    {
        return [
            'name' => $this->getName(),
            'ready' => $this->ready,
            'methods' => [
                'recordCounter' => [
                    'parameters' => ['name' => 'string', 'value' => 'int', 'attributes' => 'array'],
                    'return' => 'void',
                    'description' => 'Record a counter metric',
                ],
                'recordGauge' => [
                    'parameters' => ['name' => 'string', 'value' => 'float', 'attributes' => 'array'],
                    'return' => 'void',
                    'description' => 'Record a gauge metric',
                ],
                'recordHistogram' => [
                    'parameters' => ['name' => 'string', 'value' => 'float', 'attributes' => 'array'],
                    'return' => 'void',
                    'description' => 'Record a histogram metric',
                ],
                'flush' => [
                    'parameters' => [],
                    'return' => 'void',
                    'description' => 'Flush pending metrics',
                ],
            ],
        ];
    }

    /**
     * Set the logger for telemetry operations.
     *
     * @param LoggerInterface $logger
     * @return self
     */
    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * Record a counter metric.
     *
     * Counters are cumulative metrics that only increase.
     *
     * @param string $name Metric name
     * @param int $value Value to add (default: 1)
     * @param array<string, mixed> $attributes Additional attributes
     * @return void
     */
    public function recordCounter(string $name, int $value = 1, array $attributes = []): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $key = $this->buildKey($name, $attributes);
        $this->counters[$key] = ($this->counters[$key] ?? 0) + $value;

        $this->logger->debug("Counter: {$name} = {$value}", $attributes);
    }

    /**
     * Record a gauge metric.
     *
     * Gauges are metrics that can go up and down.
     *
     * @param string $name Metric name
     * @param float $value Current value
     * @param array<string, mixed> $attributes Additional attributes
     * @return void
     */
    public function recordGauge(string $name, float $value, array $attributes = []): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $key = $this->buildKey($name, $attributes);
        $this->gauges[$key] = $value;

        $this->logger->debug("Gauge: {$name} = {$value}", $attributes);
    }

    /**
     * Record a histogram metric.
     *
     * Histograms track the distribution of values.
     *
     * @param string $name Metric name
     * @param float $value Value to record
     * @param array<string, mixed> $attributes Additional attributes
     * @return void
     */
    public function recordHistogram(string $name, float $value, array $attributes = []): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $key = $this->buildKey($name, $attributes);

        if (! isset($this->histograms[$key])) {
            $this->histograms[$key] = [];
        }

        $this->histograms[$key][] = $value;

        $this->logger->debug("Histogram: {$name} = {$value}", $attributes);
    }

    /**
     * Record an agent request.
     *
     * Helper method for recording agent-specific metrics.
     *
     * @param bool $success Whether the request succeeded
     * @param int $tokensInput Input tokens used
     * @param int $tokensOutput Output tokens used
     * @param float $duration Request duration in milliseconds
     * @param string|null $error Error message if failed
     * @return void
     */
    public function recordAgentRequest(
        bool $success,
        int $tokensInput = 0,
        int $tokensOutput = 0,
        float $duration = 0,
        ?string $error = null
    ): void {
        $this->metrics->recordRequest($success, $tokensInput, $tokensOutput, $duration, $error);

        // Also record as telemetry metrics
        $this->recordCounter('agent.requests.total');

        if ($success) {
            $this->recordCounter('agent.requests.success');
        } else {
            $this->recordCounter('agent.requests.failed');
        }

        if ($tokensInput > 0) {
            $this->recordHistogram('agent.tokens.input', $tokensInput);
        }

        if ($tokensOutput > 0) {
            $this->recordHistogram('agent.tokens.output', $tokensOutput);
        }

        if ($duration > 0) {
            $this->recordHistogram('agent.duration.ms', $duration);
        }
    }

    /**
     * Get the metrics summary.
     *
     * @return array<string, mixed>
     */
    public function getSummary(): array
    {
        return $this->metrics->getSummary();
    }

    /**
     * Get all collected metrics.
     *
     * @return array{counters: array, gauges: array, histograms: array}
     */
    public function getAllMetrics(): array
    {
        return [
            'counters' => $this->counters,
            'gauges' => $this->gauges,
            'histograms' => array_map(function ($values) {
                return [
                    'count' => count($values),
                    'sum' => array_sum($values),
                    'min' => min($values),
                    'max' => max($values),
                    'avg' => array_sum($values) / count($values),
                ];
            }, $this->histograms),
        ];
    }

    /**
     * Flush pending metrics.
     *
     * Sends metrics to configured exporters.
     *
     * @return void
     */
    public function flush(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        // In a real implementation, this would send metrics to an OTLP endpoint
        $endpoint = $this->settings->get('telemetry.otlp.endpoint');

        if ($endpoint !== null) {
            $this->logger->info('Flushing telemetry metrics', [
                'endpoint' => $endpoint,
                'counters' => count($this->counters),
                'gauges' => count($this->gauges),
                'histograms' => count($this->histograms),
            ]);

            // TODO: Implement actual OTLP export
        }
    }

    /**
     * Reset all metrics.
     *
     * @return void
     */
    public function reset(): void
    {
        $this->counters = [];
        $this->gauges = [];
        $this->histograms = [];
        $this->metrics->reset();
    }

    /**
     * Get the underlying Metrics instance.
     *
     * @return Metrics
     */
    public function getMetrics(): Metrics
    {
        return $this->metrics;
    }

    /**
     * Check if telemetry is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->ready && $this->settings->get('telemetry.enabled', false);
    }

    /**
     * Build a metric key with attributes.
     *
     * @param string $name Metric name
     * @param array<string, mixed> $attributes Attributes
     * @return string
     */
    private function buildKey(string $name, array $attributes): string
    {
        if (empty($attributes)) {
            return $name;
        }

        ksort($attributes);
        $parts = [$name];

        foreach ($attributes as $key => $value) {
            $parts[] = "{$key}={$value}";
        }

        return implode('|', $parts);
    }
}
