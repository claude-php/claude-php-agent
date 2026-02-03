<?php

declare(strict_types=1);

namespace ClaudeAgents\Services\Tracing;

use ClaudeAgents\Services\ServiceInterface;
use ClaudeAgents\Services\Settings\SettingsService;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Tracing service for integrating with observability platforms.
 *
 * Supports LangSmith, LangFuse, Arize Phoenix, and custom tracers.
 */
class TracingService implements ServiceInterface
{
    private bool $ready = false;

    /**
     * @var array<string, TracerInterface> Active tracers
     */
    private array $tracers = [];

    /**
     * @var array<string, TraceContext> Active trace contexts
     */
    private array $contexts = [];

    private LoggerInterface $logger;

    /**
     * @param SettingsService $settings Settings service
     */
    public function __construct(
        private SettingsService $settings
    ) {
        $this->logger = new NullLogger();
    }

    public function getName(): string
    {
        return 'tracing';
    }

    public function initialize(): void
    {
        if ($this->ready) {
            return;
        }

        // Check if tracing is enabled
        if (! $this->settings->get('tracing.enabled', false)) {
            $this->ready = true;

            return;
        }

        // Initialize configured tracers
        $providers = $this->settings->get('tracing.providers', []);

        foreach ($providers as $provider) {
            try {
                $this->initializeTracer($provider);
            } catch (\Throwable $e) {
                $this->logger->error("Failed to initialize tracer {$provider}: {$e->getMessage()}");
            }
        }

        $this->ready = true;
    }

    public function teardown(): void
    {
        // Flush all active traces
        foreach ($this->contexts as $traceId => $context) {
            try {
                $this->endTrace($traceId);
            } catch (\Throwable $e) {
                $this->logger->error("Error ending trace {$traceId}: {$e->getMessage()}");
            }
        }

        $this->tracers = [];
        $this->contexts = [];
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
                'startTrace' => [
                    'parameters' => ['traceId' => 'string', 'traceName' => 'string', 'metadata' => 'array'],
                    'return' => 'void',
                    'description' => 'Start a new trace',
                ],
                'endTrace' => [
                    'parameters' => ['traceId' => 'string', 'outputs' => 'array'],
                    'return' => 'void',
                    'description' => 'End a trace',
                ],
                'recordSpan' => [
                    'parameters' => ['spanName' => 'string', 'callback' => 'callable'],
                    'return' => 'mixed',
                    'description' => 'Record a span with automatic timing',
                ],
                'recordMetric' => [
                    'parameters' => ['name' => 'string', 'value' => 'float', 'tags' => 'array'],
                    'return' => 'void',
                    'description' => 'Record a metric',
                ],
            ],
        ];
    }

    /**
     * Set the logger for tracing operations.
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
     * Start a new trace.
     *
     * @param string $traceId Unique trace identifier
     * @param string $traceName Human-readable trace name
     * @param array<string, mixed> $metadata Additional metadata
     * @return void
     */
    public function startTrace(string $traceId, string $traceName, array $metadata = []): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $context = new TraceContext(
            traceId: $traceId,
            traceName: $traceName,
            metadata: $this->sanitizeMetadata($metadata),
            startTime: microtime(true)
        );

        $this->contexts[$traceId] = $context;

        // Notify all tracers
        foreach ($this->tracers as $tracer) {
            try {
                $tracer->startTrace($context);
            } catch (\Throwable $e) {
                $this->logger->error("Tracer error in startTrace: {$e->getMessage()}");
            }
        }

        $this->logger->debug("Started trace: {$traceName}", ['trace_id' => $traceId]);
    }

    /**
     * End a trace.
     *
     * @param string $traceId Trace identifier
     * @param array<string, mixed> $outputs Trace outputs/results
     * @return void
     */
    public function endTrace(string $traceId, array $outputs = []): void
    {
        if (! $this->isEnabled() || ! isset($this->contexts[$traceId])) {
            return;
        }

        $context = $this->contexts[$traceId];
        $context->endTime = microtime(true);
        $context->outputs = $this->sanitizeMetadata($outputs);

        // Notify all tracers
        foreach ($this->tracers as $tracer) {
            try {
                $tracer->endTrace($context);
            } catch (\Throwable $e) {
                $this->logger->error("Tracer error in endTrace: {$e->getMessage()}");
            }
        }

        $duration = ($context->endTime - $context->startTime) * 1000;
        $this->logger->debug("Ended trace: {$context->traceName}", [
            'trace_id' => $traceId,
            'duration_ms' => $duration,
        ]);

        unset($this->contexts[$traceId]);
    }

    /**
     * Record a span with automatic timing.
     *
     * Executes the callback and records timing information.
     *
     * @param string $spanName Span name
     * @param callable $callback Callback to execute
     * @param array<string, mixed> $metadata Additional metadata
     * @return mixed Callback return value
     */
    public function recordSpan(string $spanName, callable $callback, array $metadata = []): mixed
    {
        if (! $this->isEnabled()) {
            return $callback();
        }

        $spanId = uniqid('span_', true);
        $startTime = microtime(true);

        try {
            $result = $callback();
            $success = true;
            $error = null;
        } catch (\Throwable $e) {
            $success = false;
            $error = $e->getMessage();
            throw $e;
        } finally {
            $endTime = microtime(true);
            $duration = ($endTime - $startTime) * 1000;

            $span = new Span(
                spanId: $spanId,
                spanName: $spanName,
                startTime: $startTime,
                endTime: $endTime,
                duration: $duration,
                metadata: $this->sanitizeMetadata($metadata),
                success: $success ?? true,
                error: $error ?? null
            );

            // Record span with all tracers
            foreach ($this->tracers as $tracer) {
                try {
                    $tracer->recordSpan($span);
                } catch (\Throwable $e) {
                    $this->logger->error("Tracer error in recordSpan: {$e->getMessage()}");
                }
            }
        }

        return $result;
    }

    /**
     * Record a metric.
     *
     * @param string $name Metric name
     * @param float $value Metric value
     * @param array<string, mixed> $tags Additional tags
     * @return void
     */
    public function recordMetric(string $name, float $value, array $tags = []): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $metric = new Metric(
            name: $name,
            value: $value,
            tags: $tags,
            timestamp: microtime(true)
        );

        foreach ($this->tracers as $tracer) {
            try {
                $tracer->recordMetric($metric);
            } catch (\Throwable $e) {
                $this->logger->error("Tracer error in recordMetric: {$e->getMessage()}");
            }
        }
    }

    /**
     * Get an active trace context.
     *
     * @param string $traceId Trace identifier
     * @return TraceContext|null
     */
    public function getContext(string $traceId): ?TraceContext
    {
        return $this->contexts[$traceId] ?? null;
    }

    /**
     * Check if tracing is enabled and has active tracers.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->ready && $this->settings->get('tracing.enabled', false);
    }

    /**
     * Initialize a tracer by provider name.
     *
     * @param string $provider Provider name (langsmith, langfuse, phoenix, etc.)
     * @return void
     * @throws \RuntimeException If provider not supported
     */
    private function initializeTracer(string $provider): void
    {
        $tracer = match ($provider) {
            'langsmith' => $this->createLangSmithTracer(),
            'langfuse' => $this->createLangFuseTracer(),
            'phoenix' => $this->createPhoenixTracer(),
            default => throw new \RuntimeException("Unsupported tracing provider: {$provider}"),
        };

        $this->tracers[$provider] = $tracer;
        $this->logger->debug("Initialized tracer: {$provider}");
    }

    /**
     * Create a LangSmith tracer.
     *
     * @return TracerInterface
     */
    private function createLangSmithTracer(): TracerInterface
    {
        return new LangSmithTracer(
            apiKey: $this->settings->get('tracing.langsmith.api_key'),
            projectName: $this->settings->get('tracing.langsmith.project', 'default'),
            endpoint: $this->settings->get('tracing.langsmith.endpoint', 'https://api.smith.langchain.com'),
            logger: $this->logger
        );
    }

    /**
     * Create a LangFuse tracer.
     *
     * @return TracerInterface
     */
    private function createLangFuseTracer(): TracerInterface
    {
        return new LangFuseTracer(
            publicKey: $this->settings->get('tracing.langfuse.public_key'),
            secretKey: $this->settings->get('tracing.langfuse.secret_key'),
            endpoint: $this->settings->get('tracing.langfuse.endpoint', 'https://cloud.langfuse.com'),
            logger: $this->logger
        );
    }

    /**
     * Create an Arize Phoenix tracer.
     *
     * @return TracerInterface
     */
    private function createPhoenixTracer(): TracerInterface
    {
        return new PhoenixTracer(
            endpoint: $this->settings->get('tracing.phoenix.endpoint', 'http://localhost:6006'),
            logger: $this->logger
        );
    }

    /**
     * Sanitize metadata to remove sensitive information.
     *
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function sanitizeMetadata(array $metadata): array
    {
        $sensitiveKeys = ['api_key', 'password', 'token', 'secret', 'authorization'];

        return array_map(function ($value) use ($sensitiveKeys) {
            if (is_array($value)) {
                return $this->sanitizeMetadata($value);
            }

            return $value;
        }, array_filter($metadata, function ($key) use ($sensitiveKeys) {
            $keyLower = strtolower((string) $key);
            foreach ($sensitiveKeys as $sensitive) {
                if (str_contains($keyLower, $sensitive)) {
                    return false;
                }
            }

            return true;
        }, ARRAY_FILTER_USE_KEY));
    }
}
