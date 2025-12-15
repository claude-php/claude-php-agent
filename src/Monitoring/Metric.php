<?php

declare(strict_types=1);

namespace ClaudeAgents\Monitoring;

/**
 * Represents a monitored metric with value and metadata.
 */
class Metric
{
    private string $name;
    private mixed $value;
    private array $metadata;
    private float $timestamp;

    /**
     * @param string $name Metric name
     * @param mixed $value Metric value
     * @param array<string, mixed> $metadata Additional metadata
     */
    public function __construct(string $name, mixed $value, array $metadata = [])
    {
        $this->name = $name;
        $this->value = $value;
        $this->metadata = $metadata;
        $this->timestamp = microtime(true);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getTimestamp(): float
    {
        return $this->timestamp;
    }

    /**
     * Check if metric exceeds threshold.
     */
    public function exceedsThreshold(float $threshold, string $operator = '>'): bool
    {
        if (! is_numeric($this->value)) {
            return false;
        }

        return match ($operator) {
            '>' => $this->value > $threshold,
            '>=' => $this->value >= $threshold,
            '<' => $this->value < $threshold,
            '<=' => $this->value <= $threshold,
            '==' => $this->value == $threshold,
            '!=' => $this->value != $threshold,
            default => false,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'value' => $this->value,
            'metadata' => $this->metadata,
            'timestamp' => $this->timestamp,
        ];
    }
}
