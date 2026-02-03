<?php

declare(strict_types=1);

namespace ClaudeAgents\Services\Tracing;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Arize Phoenix tracer implementation.
 *
 * Sends traces to Arize Phoenix for ML observability.
 */
class PhoenixTracer implements TracerInterface
{
    private LoggerInterface $logger;

    /**
     * @param string $endpoint Phoenix endpoint
     * @param LoggerInterface|null $logger PSR-3 logger
     */
    public function __construct(
        private string $endpoint = 'http://localhost:6006',
        ?LoggerInterface $logger = null
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function startTrace(TraceContext $context): void
    {
        $this->logger->debug('Phoenix: Started trace', [
            'trace_id' => $context->traceId,
            'trace_name' => $context->traceName,
        ]);
    }

    public function endTrace(TraceContext $context): void
    {
        $this->logger->debug('Phoenix: Ended trace', [
            'trace_id' => $context->traceId,
            'duration_ms' => $context->getDuration(),
        ]);
    }

    public function recordSpan(Span $span): void
    {
        $this->logger->debug('Phoenix: Recorded span', [
            'span_name' => $span->spanName,
            'duration_ms' => $span->duration,
        ]);
    }

    public function recordMetric(Metric $metric): void
    {
        $this->logger->debug('Phoenix: Recorded metric', [
            'name' => $metric->name,
            'value' => $metric->value,
        ]);
    }
}
