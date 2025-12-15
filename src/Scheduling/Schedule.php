<?php

declare(strict_types=1);

namespace ClaudeAgents\Scheduling;

/**
 * Represents a task schedule (cron-like or one-time).
 */
class Schedule
{
    private ?string $cronExpression = null;
    private ?float $timestamp = null;
    private ?int $interval = null;
    private string $timezone;

    private function __construct(string $timezone = 'UTC')
    {
        $this->timezone = $timezone;
    }

    /**
     * Create a cron-based schedule.
     */
    public static function cron(string $expression, string $timezone = 'UTC'): self
    {
        $schedule = new self($timezone);
        $schedule->cronExpression = $expression;

        return $schedule;
    }

    /**
     * Create a one-time schedule at specific timestamp.
     */
    public static function at(float $timestamp, string $timezone = 'UTC'): self
    {
        $schedule = new self($timezone);
        $schedule->timestamp = $timestamp;

        return $schedule;
    }

    /**
     * Create a one-time schedule from relative time string.
     */
    public static function in(string $relativeTime, string $timezone = 'UTC'): self
    {
        $schedule = new self($timezone);
        $schedule->timestamp = strtotime($relativeTime);

        return $schedule;
    }

    /**
     * Create a recurring interval schedule.
     */
    public static function every(int $seconds, string $timezone = 'UTC'): self
    {
        $schedule = new self($timezone);
        $schedule->interval = $seconds;

        return $schedule;
    }

    /**
     * Get the next run time.
     */
    public function getNextRunTime(?float $after = null): ?float
    {
        $after ??= microtime(true);

        if ($this->cronExpression) {
            return $this->parseNextCronTime($this->cronExpression, $after);
        }

        if ($this->timestamp) {
            return $this->timestamp > $after ? $this->timestamp : null;
        }

        if ($this->interval) {
            return $after + $this->interval;
        }

        return null;
    }

    public function isRecurring(): bool
    {
        return $this->cronExpression !== null || $this->interval !== null;
    }

    public function toString(): string
    {
        if ($this->cronExpression) {
            return "cron:{$this->cronExpression}";
        }

        if ($this->timestamp) {
            return 'at:' . date('Y-m-d H:i:s', (int)$this->timestamp);
        }

        if ($this->interval) {
            return "every:{$this->interval}s";
        }

        return 'unknown';
    }

    /**
     * Parse next run time from cron expression.
     * Simplified implementation - in production, use a cron parser library.
     */
    private function parseNextCronTime(string $cron, float $after): float
    {
        // Basic cron parsing for common patterns
        // Format: minute hour day month weekday
        $parts = explode(' ', $cron);

        if (count($parts) !== 5) {
            // Invalid cron, default to 1 hour from now
            return $after + 3600;
        }

        // For simplicity, handle some common patterns
        if ($cron === '0 * * * *') { // Every hour
            $next = ceil($after / 3600) * 3600;

            return $next;
        }

        if ($cron === '0 0 * * *') { // Daily at midnight
            $tomorrow = strtotime('tomorrow midnight', (int)$after);

            return (float)$tomorrow;
        }

        // Default: run in 1 hour
        return $after + 3600;
    }
}
