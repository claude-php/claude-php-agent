<?php

declare(strict_types=1);

namespace ClaudeAgents\Observability;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;

/**
 * Structured logger with trace context propagation.
 *
 * Automatically includes trace IDs, span IDs, and structured attributes
 * in all log messages for correlation with distributed traces.
 */
class ObservabilityLogger implements LoggerInterface
{
    private LoggerInterface $logger;
    private ?Tracer $tracer;

    /**
     * @var array<string, mixed> Global context added to all logs
     */
    private array $globalContext = [];

    public function __construct(
        ?LoggerInterface $logger = null,
        ?Tracer $tracer = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->tracer = $tracer;
    }

    /**
     * Set global context.
     *
     * @param array<string, mixed> $context
     */
    public function setGlobalContext(array $context): self
    {
        $this->globalContext = array_merge($this->globalContext, $context);

        return $this;
    }

    /**
     * Clear global context.
     */
    public function clearGlobalContext(): self
    {
        $this->globalContext = [];

        return $this;
    }

    public function emergency($message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    public function alert($message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    public function critical($message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    public function error($message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function warning($message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function notice($message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    public function info($message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function debug($message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    public function log($level, $message, array $context = []): void
    {
        // Merge with global context
        $enrichedContext = array_merge($this->globalContext, $context);

        // Add trace context if available
        if ($this->tracer !== null) {
            $traceId = $this->tracer->getCurrentTraceId();
            if ($traceId !== null) {
                $enrichedContext['trace_id'] = $traceId;
            }

            // Add active span IDs
            $activeSpans = $this->tracer->getActiveSpans();
            if (! empty($activeSpans)) {
                $enrichedContext['span_id'] = $activeSpans[0]->getSpanId();
            }
        }

        // Add timestamp
        $enrichedContext['timestamp'] = microtime(true);

        // Add memory usage
        $enrichedContext['memory_usage'] = memory_get_usage(true);

        $this->logger->log($level, $message, $enrichedContext);
    }

    /**
     * Log with structured fields.
     *
     * @param string $level Log level
     * @param string $message Message
     * @param array<string, mixed> $fields Structured fields
     */
    public function logStructured(string $level, string $message, array $fields = []): void
    {
        $this->log($level, $message, ['fields' => $fields]);
    }

    /**
     * Log an event (for structured logging).
     *
     * @param string $eventName Event name
     * @param array<string, mixed> $attributes Event attributes
     */
    public function logEvent(string $eventName, array $attributes = []): void
    {
        $this->info($eventName, [
            'event' => $eventName,
            'attributes' => $attributes,
        ]);
    }

    /**
     * Log an error with exception context.
     */
    public function logException(\Throwable $e, string $message = 'Exception occurred', array $context = []): void
    {
        $this->error($message, array_merge($context, [
            'exception' => [
                'class' => get_class($e),
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ],
        ]));
    }

    /**
     * Log agent execution metrics.
     *
     * @param array<string, mixed> $metrics
     */
    public function logMetrics(array $metrics): void
    {
        $this->info('Metrics recorded', [
            'metrics' => $metrics,
        ]);
    }

    /**
     * Get the underlying logger.
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Set tracer for context propagation.
     */
    public function setTracer(Tracer $tracer): self
    {
        $this->tracer = $tracer;

        return $this;
    }

    /**
     * Get tracer.
     */
    public function getTracer(): ?Tracer
    {
        return $this->tracer;
    }
}
