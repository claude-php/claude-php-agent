<?php

declare(strict_types=1);

namespace ClaudeAgents\Services\Tracing;

/**
 * Represents a span within a trace.
 */
class Span
{
    /**
     * @param string $spanId Unique span identifier
     * @param string $spanName Span name
     * @param float $startTime Start time (microtime)
     * @param float $endTime End time (microtime)
     * @param float $duration Duration in milliseconds
     * @param array<string, mixed> $metadata Additional metadata
     * @param bool $success Whether span succeeded
     * @param string|null $error Error message if failed
     */
    public function __construct(
        public readonly string $spanId,
        public readonly string $spanName,
        public readonly float $startTime,
        public readonly float $endTime,
        public readonly float $duration,
        public readonly array $metadata,
        public readonly bool $success,
        public readonly ?string $error
    ) {
    }
}
