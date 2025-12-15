<?php

declare(strict_types=1);

namespace ClaudeAgents\Monitoring;

/**
 * Represents an alert generated from monitored metrics.
 */
class Alert
{
    public const SEVERITY_INFO = 'info';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_ERROR = 'error';
    public const SEVERITY_CRITICAL = 'critical';

    private string $id;
    private string $title;
    private string $message;
    private string $severity;
    private ?Metric $metric;
    private array $context;
    private float $timestamp;

    /**
     * @param string $title Alert title
     * @param string $message Alert message
     * @param string $severity Severity level
     * @param Metric|null $metric Related metric
     * @param array<string, mixed> $context Additional context
     */
    public function __construct(
        string $title,
        string $message,
        string $severity = self::SEVERITY_INFO,
        ?Metric $metric = null,
        array $context = []
    ) {
        $this->id = uniqid('alert_', true);
        $this->title = $title;
        $this->message = $message;
        $this->severity = $severity;
        $this->metric = $metric;
        $this->context = $context;
        $this->timestamp = microtime(true);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getSeverity(): string
    {
        return $this->severity;
    }

    public function getMetric(): ?Metric
    {
        return $this->metric;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    public function getTimestamp(): float
    {
        return $this->timestamp;
    }

    public function isCritical(): bool
    {
        return $this->severity === self::SEVERITY_CRITICAL;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'message' => $this->message,
            'severity' => $this->severity,
            'metric' => $this->metric?->toArray(),
            'context' => $this->context,
            'timestamp' => $this->timestamp,
        ];
    }
}
