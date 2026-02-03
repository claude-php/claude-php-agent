<?php

declare(strict_types=1);

namespace ClaudeAgents\Services\Tracing;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * LangSmith tracer implementation.
 *
 * Sends traces to LangSmith for analysis and monitoring.
 */
class LangSmithTracer implements TracerInterface
{
    private LoggerInterface $logger;

    /**
     * @param string|null $apiKey LangSmith API key
     * @param string $projectName Project name
     * @param string $endpoint API endpoint
     * @param LoggerInterface|null $logger PSR-3 logger
     */
    public function __construct(
        private ?string $apiKey,
        private string $projectName = 'default',
        private string $endpoint = 'https://api.smith.langchain.com',
        ?LoggerInterface $logger = null
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function startTrace(TraceContext $context): void
    {
        // Implementation would send HTTP request to LangSmith API
        $this->logger->debug('LangSmith: Started trace', [
            'trace_id' => $context->traceId,
            'trace_name' => $context->traceName,
        ]);
    }

    public function endTrace(TraceContext $context): void
    {
        // Implementation would send HTTP request to LangSmith API
        $this->logger->debug('LangSmith: Ended trace', [
            'trace_id' => $context->traceId,
            'duration_ms' => $context->getDuration(),
        ]);
    }

    public function recordSpan(Span $span): void
    {
        // Implementation would send HTTP request to LangSmith API
        $this->logger->debug('LangSmith: Recorded span', [
            'span_name' => $span->spanName,
            'duration_ms' => $span->duration,
        ]);
    }

    public function recordMetric(Metric $metric): void
    {
        // Implementation would send HTTP request to LangSmith API
        $this->logger->debug('LangSmith: Recorded metric', [
            'name' => $metric->name,
            'value' => $metric->value,
        ]);
    }
}
