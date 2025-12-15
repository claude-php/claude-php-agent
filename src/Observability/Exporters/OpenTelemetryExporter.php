<?php

declare(strict_types=1);

namespace ClaudeAgents\Observability\Exporters;

/**
 * Export traces to OpenTelemetry-compatible backends.
 *
 * Supports OTLP/HTTP and OTLP/gRPC protocols.
 */
class OpenTelemetryExporter implements ExporterInterface
{
    private const DEFAULT_ENDPOINT = 'http://localhost:4318/v1/traces';

    public function __construct(
        private readonly string $endpoint = self::DEFAULT_ENDPOINT,
        private readonly array $headers = [],
        private readonly int $timeout = 10,
    ) {
    }

    public function export(array $data): bool
    {
        // Convert to OTLP format
        $payload = $this->convertToOTLP($data);

        try {
            $ch = curl_init($this->endpoint);

            $headers = array_merge(
                ['Content-Type: application/json'],
                $this->formatHeaders($this->headers)
            );

            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeout,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return $httpCode >= 200 && $httpCode < 300;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Convert data to OTLP format.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function convertToOTLP(array $data): array
    {
        // If already in OTLP format (from Tracer::toOpenTelemetry()), return as-is
        if (isset($data['resourceSpans'])) {
            return $data;
        }

        // Otherwise, convert basic format
        $spans = [];
        if (isset($data['spans'])) {
            foreach ($data['spans'] as $span) {
                $spans[] = $this->convertSpanToOTLP($span);
            }
        }

        return [
            'resourceSpans' => [
                [
                    'resource' => [
                        'attributes' => [
                            ['key' => 'service.name', 'value' => ['stringValue' => 'claude-agent']],
                            ['key' => 'service.version', 'value' => ['stringValue' => '1.0.0']],
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
                ],
            ],
        ];
    }

    /**
     * Convert a single span to OTLP format.
     *
     * @param array<string, mixed> $span
     * @return array<string, mixed>
     */
    private function convertSpanToOTLP(array $span): array
    {
        return [
            'traceId' => $span['trace_id'] ?? '',
            'spanId' => $span['span_id'] ?? '',
            'parentSpanId' => $span['parent_span_id'] ?? null,
            'name' => $span['name'] ?? 'unknown',
            'kind' => 'SPAN_KIND_INTERNAL',
            'startTimeUnixNano' => isset($span['start_time']) ? (int)($span['start_time'] * 1_000_000) : 0,
            'endTimeUnixNano' => isset($span['end_time']) ? (int)($span['end_time'] * 1_000_000) : 0,
            'attributes' => $this->convertAttributes($span['attributes'] ?? []),
            'status' => [
                'code' => $span['status'] ?? 'UNSET',
                'message' => $span['status_message'] ?? null,
            ],
            'events' => $this->convertEvents($span['events'] ?? []),
        ];
    }

    /**
     * Convert attributes to OTLP format.
     *
     * @param array<string, mixed> $attributes
     * @return array<array{key: string, value: array}>
     */
    private function convertAttributes(array $attributes): array
    {
        $result = [];

        foreach ($attributes as $key => $value) {
            $result[] = [
                'key' => $key,
                'value' => match (true) {
                    is_string($value) => ['stringValue' => $value],
                    is_int($value) => ['intValue' => $value],
                    is_float($value) => ['doubleValue' => $value],
                    is_bool($value) => ['boolValue' => $value],
                    is_array($value) => ['stringValue' => json_encode($value)],
                    default => ['stringValue' => (string)$value],
                },
            ];
        }

        return $result;
    }

    /**
     * Convert events to OTLP format.
     *
     * @param array<array{name: string, timestamp: float, attributes: array}> $events
     * @return array<array{name: string, timeUnixNano: int, attributes: array}>
     */
    private function convertEvents(array $events): array
    {
        return array_map(fn ($event) => [
            'name' => $event['name'],
            'timeUnixNano' => (int)($event['timestamp'] * 1_000_000),
            'attributes' => $this->convertAttributes($event['attributes'] ?? []),
        ], $events);
    }

    /**
     * Format headers for curl.
     *
     * @param array<string, string> $headers
     * @return array<string>
     */
    private function formatHeaders(array $headers): array
    {
        $formatted = [];
        foreach ($headers as $key => $value) {
            $formatted[] = "{$key}: {$value}";
        }

        return $formatted;
    }

    public function getName(): string
    {
        return 'opentelemetry';
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }
}
