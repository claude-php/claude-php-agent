<?php

declare(strict_types=1);

namespace ClaudeAgents\Services\Tracing;

/**
 * Interface for tracing backends.
 */
interface TracerInterface
{
    /**
     * Start a trace.
     *
     * @param TraceContext $context Trace context
     * @return void
     */
    public function startTrace(TraceContext $context): void;

    /**
     * End a trace.
     *
     * @param TraceContext $context Trace context with outputs
     * @return void
     */
    public function endTrace(TraceContext $context): void;

    /**
     * Record a span.
     *
     * @param Span $span Span to record
     * @return void
     */
    public function recordSpan(Span $span): void;

    /**
     * Record a metric.
     *
     * @param Metric $metric Metric to record
     * @return void
     */
    public function recordMetric(Metric $metric): void;
}
