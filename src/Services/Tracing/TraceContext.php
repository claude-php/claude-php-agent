<?php

declare(strict_types=1);

namespace ClaudeAgents\Services\Tracing;

/**
 * Context for a trace.
 */
class TraceContext
{
    /**
     * @param string $traceId Unique trace identifier
     * @param string $traceName Human-readable trace name
     * @param array<string, mixed> $metadata Additional metadata
     * @param float $startTime Start time (microtime)
     * @param float|null $endTime End time (microtime)
     * @param array<string, mixed> $outputs Trace outputs
     */
    public function __construct(
        public readonly string $traceId,
        public readonly string $traceName,
        public readonly array $metadata,
        public readonly float $startTime,
        public ?float $endTime = null,
        public array $outputs = []
    ) {
    }

    /**
     * Get the duration in milliseconds.
     *
     * @return float|null Duration or null if trace not ended
     */
    public function getDuration(): ?float
    {
        if ($this->endTime === null) {
            return null;
        }

        return ($this->endTime - $this->startTime) * 1000;
    }
}
