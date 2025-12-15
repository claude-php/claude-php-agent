<?php

declare(strict_types=1);

namespace ClaudeAgents\Observability;

/**
 * Traces execution for debugging and monitoring.
 *
 * Supports distributed tracing with parent-child span relationships,
 * trace IDs, and OpenTelemetry-compatible output.
 */
class Tracer
{
    /**
     * @var array<string, Span> Active spans by span ID
     */
    private array $spans = [];

    /**
     * @var array<Span> Completed spans
     */
    private array $completedSpans = [];

    /**
     * @var string|null Current trace ID
     */
    private ?string $currentTraceId = null;

    /**
     * Start a new span.
     *
     * @param string $name Span name
     * @param array<string, mixed> $attributes Span attributes
     * @param Span|null $parent Parent span (for hierarchy)
     * @return Span The created span
     */
    public function startSpan(string $name, array $attributes = [], ?Span $parent = null): Span
    {
        // If no parent and no current trace, start new trace
        if ($parent === null && $this->currentTraceId === null) {
            $this->currentTraceId = $this->generateTraceId();
        }

        $span = new Span($name, $attributes, $parent, $this->currentTraceId);
        $this->spans[$span->getSpanId()] = $span;

        return $span;
    }

    /**
     * End a span.
     */
    public function endSpan(Span $span): void
    {
        $span->end();

        // Remove from active spans and add to completed
        unset($this->spans[$span->getSpanId()]);
        $this->completedSpans[] = $span;
    }

    /**
     * Get all completed spans.
     *
     * @return array<Span>
     */
    public function getSpans(): array
    {
        return $this->completedSpans;
    }

    /**
     * Get spans by trace ID.
     *
     * @return array<Span>
     */
    public function getSpansByTraceId(string $traceId): array
    {
        return array_filter(
            $this->completedSpans,
            fn (Span $span) => $span->getTraceId() === $traceId
        );
    }

    /**
     * Get active spans.
     *
     * @return array<Span>
     */
    public function getActiveSpans(): array
    {
        return array_values($this->spans);
    }

    /**
     * Get span by ID.
     */
    public function getSpan(string $spanId): ?Span
    {
        return $this->spans[$spanId] ?? null;
    }

    /**
     * Get current trace ID.
     */
    public function getCurrentTraceId(): ?string
    {
        return $this->currentTraceId;
    }

    /**
     * Start a new trace.
     */
    public function startTrace(?string $traceId = null): string
    {
        $this->currentTraceId = $traceId ?? $this->generateTraceId();

        return $this->currentTraceId;
    }

    /**
     * End the current trace.
     */
    public function endTrace(): void
    {
        // End all active spans in current trace
        foreach ($this->spans as $span) {
            if ($span->getTraceId() === $this->currentTraceId && $span->isRecording()) {
                $this->endSpan($span);
            }
        }

        $this->currentTraceId = null;
    }

    /**
     * Get trace as array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'trace_id' => $this->currentTraceId,
            'spans' => array_map(fn ($s) => $s->toArray(), $this->completedSpans),
            'span_count' => count($this->completedSpans),
            'total_duration' => $this->getTotalDuration(),
            'active_spans' => count($this->spans),
        ];
    }

    /**
     * Export to OpenTelemetry format.
     *
     * @return array<string, mixed>
     */
    public function toOpenTelemetry(): array
    {
        $traces = [];

        // Group spans by trace ID
        foreach ($this->completedSpans as $span) {
            $traceId = $span->getTraceId();
            if (! isset($traces[$traceId])) {
                $traces[$traceId] = [];
            }
            $traces[$traceId][] = $span->toOpenTelemetry();
        }

        return [
            'resourceSpans' => array_map(fn ($traceId, $spans) => [
                'resource' => [
                    'attributes' => [
                        ['key' => 'service.name', 'value' => ['stringValue' => 'claude-agent']],
                    ],
                ],
                'scopeSpans' => [
                    [
                        'scope' => [
                            'name' => 'ClaudeAgents',
                            'version' => '1.0.0',
                        ],
                        'spans' => $spans,
                    ],
                ],
            ], array_keys($traces), array_values($traces)),
        ];
    }

    /**
     * Get total duration of all spans in milliseconds.
     */
    public function getTotalDuration(): float
    {
        $total = 0;

        foreach ($this->completedSpans as $span) {
            $total += $span->getDuration();
        }

        return $total;
    }

    /**
     * Build a tree structure of spans.
     *
     * @return array<array<string, mixed>>
     */
    public function buildSpanTree(): array
    {
        $spanMap = [];
        foreach ($this->completedSpans as $span) {
            $spanMap[$span->getSpanId()] = [
                'span' => $span,
                'children' => [],
            ];
        }

        $roots = [];
        foreach ($spanMap as $spanId => &$node) {
            $parentId = $node['span']->getParentSpanId();
            if ($parentId && isset($spanMap[$parentId])) {
                $spanMap[$parentId]['children'][] = &$node;
            } else {
                $roots[] = &$node;
            }
        }

        return $roots;
    }

    /**
     * Reset the tracer.
     */
    public function reset(): void
    {
        $this->spans = [];
        $this->completedSpans = [];
        $this->currentTraceId = null;
    }

    private function generateTraceId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
