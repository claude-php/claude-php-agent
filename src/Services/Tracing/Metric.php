<?php

declare(strict_types=1);

namespace ClaudeAgents\Services\Tracing;

/**
 * Represents a metric.
 */
class Metric
{
    /**
     * @param string $name Metric name
     * @param float $value Metric value
     * @param array<string, mixed> $tags Additional tags
     * @param float $timestamp Timestamp (microtime)
     */
    public function __construct(
        public readonly string $name,
        public readonly float $value,
        public readonly array $tags,
        public readonly float $timestamp
    ) {
    }
}
