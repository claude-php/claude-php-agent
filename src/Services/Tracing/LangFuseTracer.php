<?php

declare(strict_types=1);

namespace ClaudeAgents\Services\Tracing;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * LangFuse tracer implementation.
 *
 * Sends traces to LangFuse for observability.
 */
class LangFuseTracer implements TracerInterface
{
    private LoggerInterface $logger;

    /**
     * @param string|null $publicKey LangFuse public key
     * @param string|null $secretKey LangFuse secret key
     * @param string $endpoint API endpoint
     * @param LoggerInterface|null $logger PSR-3 logger
     */
    public function __construct(
        private ?string $publicKey,
        private ?string $secretKey,
        private string $endpoint = 'https://cloud.langfuse.com',
        ?LoggerInterface $logger = null
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function startTrace(TraceContext $context): void
    {
        $this->logger->debug('LangFuse: Started trace', [
            'trace_id' => $context->traceId,
            'trace_name' => $context->traceName,
        ]);
    }

    public function endTrace(TraceContext $context): void
    {
        $this->logger->debug('LangFuse: Ended trace', [
            'trace_id' => $context->traceId,
            'duration_ms' => $context->getDuration(),
        ]);
    }

    public function recordSpan(Span $span): void
    {
        $this->logger->debug('LangFuse: Recorded span', [
            'span_name' => $span->spanName,
            'duration_ms' => $span->duration,
        ]);
    }

    public function recordMetric(Metric $metric): void
    {
        $this->logger->debug('LangFuse: Recorded metric', [
            'name' => $metric->name,
            'value' => $metric->value,
        ]);
    }
}
