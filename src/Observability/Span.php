<?php

declare(strict_types=1);

namespace ClaudeAgents\Observability;

/**
 * Represents a span of time in a trace.
 *
 * Supports OpenTelemetry-style span attributes including:
 * - Unique span and trace IDs
 * - Parent-child relationships
 * - Status tracking (OK, ERROR, UNSET)
 * - Events/annotations
 * - Baggage/context propagation
 */
class Span
{
    private string $spanId;
    private string $traceId;
    private ?string $parentSpanId;
    private float $startTime;
    private ?float $endTime = null;
    private string $status = 'UNSET';
    private ?string $statusMessage = null;

    /**
     * @var array<string, mixed> Mutable attributes
     */
    private array $attributes;

    /**
     * @var array<array{name: string, timestamp: float, attributes: array<string, mixed>}>
     */
    private array $events = [];

    /**
     * @param string $name Span name
     * @param array<string, mixed> $attributes Span attributes/metadata
     * @param Span|null $parent Parent span for hierarchy
     * @param string|null $traceId Trace ID (auto-generated if not provided)
     */
    public function __construct(
        private readonly string $name,
        array $attributes = [],
        ?Span $parent = null,
        ?string $traceId = null,
    ) {
        $this->spanId = $this->generateId();
        $this->traceId = $traceId ?? ($parent?->getTraceId() ?? $this->generateId());
        $this->parentSpanId = $parent?->getSpanId();
        $this->attributes = $attributes;
        $this->startTime = microtime(true) * 1000; // milliseconds
    }

    private function generateId(): string
    {
        return bin2hex(random_bytes(8));
    }

    public function getSpanId(): string
    {
        return $this->spanId;
    }

    public function getTraceId(): string
    {
        return $this->traceId;
    }

    public function getParentSpanId(): ?string
    {
        return $this->parentSpanId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Get attribute value.
     */
    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Set an attribute.
     */
    public function setAttribute(string $key, mixed $value): self
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * Set multiple attributes.
     *
     * @param array<string, mixed> $attributes
     */
    public function setAttributes(array $attributes): self
    {
        $this->attributes = array_merge($this->attributes, $attributes);

        return $this;
    }

    /**
     * Get span status.
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Set span status.
     *
     * @param string $status One of: OK, ERROR, UNSET
     * @param string|null $message Optional status message
     */
    public function setStatus(string $status, ?string $message = null): self
    {
        if (! in_array($status, ['OK', 'ERROR', 'UNSET'])) {
            throw new \InvalidArgumentException("Invalid status: {$status}. Must be OK, ERROR, or UNSET.");
        }

        $this->status = $status;
        $this->statusMessage = $message;

        return $this;
    }

    /**
     * Get status message.
     */
    public function getStatusMessage(): ?string
    {
        return $this->statusMessage;
    }

    /**
     * Add an event to the span.
     *
     * @param string $name Event name
     * @param array<string, mixed> $attributes Event attributes
     */
    public function addEvent(string $name, array $attributes = []): self
    {
        $this->events[] = [
            'name' => $name,
            'timestamp' => microtime(true) * 1000,
            'attributes' => $attributes,
        ];

        return $this;
    }

    /**
     * Get all events.
     *
     * @return array<array{name: string, timestamp: float, attributes: array<string, mixed>}>
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    /**
     * Check if span is recording (not ended).
     */
    public function isRecording(): bool
    {
        return $this->endTime === null;
    }

    /**
     * End the span.
     */
    public function end(): void
    {
        if ($this->endTime === null) {
            $this->endTime = microtime(true) * 1000;

            // Auto-set status to OK if not already set
            if ($this->status === 'UNSET') {
                $this->status = 'OK';
            }
        }
    }

    /**
     * Get span duration in milliseconds.
     */
    public function getDuration(): float
    {
        if ($this->endTime === null) {
            return microtime(true) * 1000 - $this->startTime;
        }

        return $this->endTime - $this->startTime;
    }

    /**
     * Get start time in milliseconds.
     */
    public function getStartTime(): float
    {
        return $this->startTime;
    }

    /**
     * Get end time in milliseconds.
     */
    public function getEndTime(): ?float
    {
        return $this->endTime;
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'span_id' => $this->spanId,
            'trace_id' => $this->traceId,
            'parent_span_id' => $this->parentSpanId,
            'name' => $this->name,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'duration_ms' => $this->getDuration(),
            'status' => $this->status,
            'status_message' => $this->statusMessage,
            'attributes' => $this->attributes,
            'events' => $this->events,
        ];
    }

    /**
     * Convert to OpenTelemetry format.
     *
     * @return array<string, mixed>
     */
    public function toOpenTelemetry(): array
    {
        return [
            'traceId' => $this->traceId,
            'spanId' => $this->spanId,
            'parentSpanId' => $this->parentSpanId,
            'name' => $this->name,
            'kind' => 'SPAN_KIND_INTERNAL',
            'startTimeUnixNano' => (int)($this->startTime * 1_000_000),
            'endTimeUnixNano' => $this->endTime ? (int)($this->endTime * 1_000_000) : null,
            'attributes' => $this->formatAttributesForOtel($this->attributes),
            'status' => [
                'code' => $this->status,
                'message' => $this->statusMessage,
            ],
            'events' => array_map(fn ($event) => [
                'name' => $event['name'],
                'timeUnixNano' => (int)($event['timestamp'] * 1_000_000),
                'attributes' => $this->formatAttributesForOtel($event['attributes']),
            ], $this->events),
        ];
    }

    /**
     * Format attributes for OpenTelemetry.
     *
     * @param array<string, mixed> $attributes
     * @return array<array{key: string, value: array{stringValue?: string, intValue?: int, doubleValue?: float, boolValue?: bool}}>
     */
    private function formatAttributesForOtel(array $attributes): array
    {
        $formatted = [];

        foreach ($attributes as $key => $value) {
            $formatted[] = [
                'key' => $key,
                'value' => match (true) {
                    is_string($value) => ['stringValue' => $value],
                    is_int($value) => ['intValue' => $value],
                    is_float($value) => ['doubleValue' => $value],
                    is_bool($value) => ['boolValue' => $value],
                    default => ['stringValue' => (string)$value],
                },
            ];
        }

        return $formatted;
    }
}
