<?php

declare(strict_types=1);

namespace ClaudeAgents\Support;

/**
 * Time and duration utilities.
 */
class TimeHelper
{
    /**
     * Calculate elapsed time in seconds.
     *
     * @param float $startTime Start timestamp (from microtime(true))
     * @param float|null $endTime End timestamp (null = now)
     * @return float Elapsed seconds
     */
    public static function elapsed(float $startTime, ?float $endTime = null): float
    {
        $endTime ??= microtime(true);

        return $endTime - $startTime;
    }

    /**
     * Format duration in human-readable format.
     *
     * @param float $seconds Duration in seconds
     * @param int $precision Number of decimal places
     * @return string Formatted duration
     */
    public static function formatDuration(float $seconds, int $precision = 2): string
    {
        if ($seconds < 0.001) {
            return number_format($seconds * 1_000_000, $precision) . 'µs';
        }

        if ($seconds < 1) {
            return number_format($seconds * 1000, $precision) . 'ms';
        }

        if ($seconds < 60) {
            return number_format($seconds, $precision) . 's';
        }

        if ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            $remainingSeconds = $seconds % 60;

            return "{$minutes}m " . number_format($remainingSeconds, 0) . 's';
        }

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $remainingSeconds = $seconds % 60;

        return "{$hours}h {$minutes}m " . number_format($remainingSeconds, 0) . 's';
    }

    /**
     * Format duration in long format (e.g., "2 hours, 30 minutes").
     *
     * @param float $seconds Duration in seconds
     * @return string Formatted duration
     */
    public static function formatDurationLong(float $seconds): string
    {
        $parts = [];

        if ($seconds >= 86400) {
            $days = floor($seconds / 86400);
            $parts[] = $days . ' ' . ($days === 1 ? 'day' : 'days');
            $seconds %= 86400;
        }

        if ($seconds >= 3600) {
            $hours = floor($seconds / 3600);
            $parts[] = $hours . ' ' . ($hours === 1 ? 'hour' : 'hours');
            $seconds %= 3600;
        }

        if ($seconds >= 60) {
            $minutes = floor($seconds / 60);
            $parts[] = $minutes . ' ' . ($minutes === 1 ? 'minute' : 'minutes');
            $seconds %= 60;
        }

        if ($seconds > 0 || empty($parts)) {
            $parts[] = round($seconds, 2) . ' ' . ($seconds === 1 ? 'second' : 'seconds');
        }

        return implode(', ', $parts);
    }

    /**
     * Execute a callable with a timeout.
     *
     * Note: This uses basic timeout via set_time_limit, which has limitations.
     * For true async timeouts, consider using AMPHP or ReactPHP.
     *
     * @template T
     * @param callable(): T $callable Function to execute
     * @param int $timeoutSeconds Timeout in seconds
     * @throws \RuntimeException If timeout exceeded
     * @return T Result
     */
    public static function timeout(callable $callable, int $timeoutSeconds): mixed
    {
        $oldLimit = ini_get('max_execution_time');
        set_time_limit($timeoutSeconds);

        try {
            $result = $callable();
            set_time_limit((int)$oldLimit);

            return $result;
        } catch (\Throwable $e) {
            set_time_limit((int)$oldLimit);

            throw $e;
        }
    }

    /**
     * Sleep for specified duration with microsecond precision.
     *
     * @param float $seconds Seconds to sleep
     */
    public static function sleep(float $seconds): void
    {
        usleep((int)($seconds * 1_000_000));
    }

    /**
     * Sleep until a specific timestamp.
     *
     * @param float $timestamp Target timestamp
     */
    public static function sleepUntil(float $timestamp): void
    {
        $now = microtime(true);
        if ($timestamp > $now) {
            self::sleep($timestamp - $now);
        }
    }

    /**
     * Measure execution time of a callable.
     *
     * @template T
     * @param callable(): T $callable Function to measure
     * @return array{result: T, duration: float} Result and duration in seconds
     */
    public static function measure(callable $callable): array
    {
        $start = microtime(true);
        $result = $callable();
        $duration = microtime(true) - $start;

        return [
            'result' => $result,
            'duration' => $duration,
        ];
    }

    /**
     * Get current timestamp with microsecond precision.
     *
     * @return float Current timestamp
     */
    public static function now(): float
    {
        return microtime(true);
    }

    /**
     * Convert seconds to milliseconds.
     *
     * @param float $seconds Seconds
     * @return int Milliseconds
     */
    public static function toMilliseconds(float $seconds): int
    {
        return (int)($seconds * 1000);
    }

    /**
     * Convert milliseconds to seconds.
     *
     * @param int $milliseconds Milliseconds
     * @return float Seconds
     */
    public static function fromMilliseconds(int $milliseconds): float
    {
        return $milliseconds / 1000;
    }

    /**
     * Convert seconds to microseconds.
     *
     * @param float $seconds Seconds
     * @return int Microseconds
     */
    public static function toMicroseconds(float $seconds): int
    {
        return (int)($seconds * 1_000_000);
    }

    /**
     * Convert microseconds to seconds.
     *
     * @param int $microseconds Microseconds
     * @return float Seconds
     */
    public static function fromMicroseconds(int $microseconds): float
    {
        return $microseconds / 1_000_000;
    }

    /**
     * Parse duration string to seconds (e.g., "1h30m", "90s").
     *
     * @param string $duration Duration string
     * @throws \InvalidArgumentException If format is invalid
     * @return float Duration in seconds
     */
    public static function parseDuration(string $duration): float
    {
        $pattern = '/^(?:(\d+)d)?(?:(\d+)h)?(?:(\d+)m)?(?:(\d+(?:\.\d+)?)s)?$/';

        if (! preg_match($pattern, $duration, $matches)) {
            throw new \InvalidArgumentException("Invalid duration format: {$duration}");
        }

        $days = (int)($matches[1] ?? 0);
        $hours = (int)($matches[2] ?? 0);
        $minutes = (int)($matches[3] ?? 0);
        $seconds = (float)($matches[4] ?? 0);

        return ($days * 86400) + ($hours * 3600) + ($minutes * 60) + $seconds;
    }

    /**
     * Create a throttle callback that limits execution rate.
     *
     * @param callable $callback Function to throttle
     * @param float $intervalSeconds Minimum interval between calls
     * @return callable Throttled function
     */
    public static function throttle(callable $callback, float $intervalSeconds): callable
    {
        $lastCall = 0.0;

        return function (...$args) use ($callback, $intervalSeconds, &$lastCall) {
            $now = microtime(true);
            $elapsed = $now - $lastCall;

            if ($elapsed < $intervalSeconds) {
                self::sleep($intervalSeconds - $elapsed);
            }

            $lastCall = microtime(true);

            return $callback(...$args);
        };
    }

    /**
     * Create a debounce callback that delays execution.
     *
     * @param callable $callback Function to debounce
     * @param float $delaySeconds Delay in seconds
     * @return callable Debounced function
     */
    public static function debounce(callable $callback, float $delaySeconds): callable
    {
        $timerId = null;

        return function (...$args) use ($callback, $delaySeconds, &$timerId) {
            if ($timerId !== null) {
                // In a real implementation, you'd cancel the timer
                // This is a simplified version
            }

            self::sleep($delaySeconds);

            return $callback(...$args);
        };
    }

    /**
     * Retry a callable with exponential backoff.
     *
     * @template T
     * @param callable(): T $callable Function to retry
     * @param int $maxAttempts Maximum retry attempts
     * @param float $initialDelay Initial delay in seconds
     * @param float $multiplier Backoff multiplier
     * @throws \Throwable If all retries fail
     * @return T Result
     */
    public static function retry(
        callable $callable,
        int $maxAttempts = 3,
        float $initialDelay = 1.0,
        float $multiplier = 2.0
    ): mixed {
        $attempt = 0;
        $delay = $initialDelay;
        $lastException = null;

        while ($attempt < $maxAttempts) {
            $attempt++;

            try {
                return $callable();
            } catch (\Throwable $e) {
                $lastException = $e;

                if ($attempt >= $maxAttempts) {
                    throw $e;
                }

                self::sleep($delay);
                $delay *= $multiplier;
            }
        }

        throw $lastException ?? new \RuntimeException('Retry failed unexpectedly');
    }

    /**
     * Get time ago in human-readable format.
     *
     * @param int $timestamp Unix timestamp
     * @param int|null $now Current timestamp (null = now)
     * @return string Time ago string
     */
    public static function ago(int $timestamp, ?int $now = null): string
    {
        $now ??= time();
        $diff = $now - $timestamp;

        if ($diff < 60) {
            return 'just now';
        }

        if ($diff < 3600) {
            $minutes = floor($diff / 60);

            return $minutes . ' ' . ($minutes === 1 ? 'minute' : 'minutes') . ' ago';
        }

        if ($diff < 86400) {
            $hours = floor($diff / 3600);

            return $hours . ' ' . ($hours === 1 ? 'hour' : 'hours') . ' ago';
        }

        if ($diff < 604800) {
            $days = floor($diff / 86400);

            return $days . ' ' . ($days === 1 ? 'day' : 'days') . ' ago';
        }

        if ($diff < 2592000) {
            $weeks = floor($diff / 604800);

            return $weeks . ' ' . ($weeks === 1 ? 'week' : 'weeks') . ' ago';
        }

        if ($diff < 31536000) {
            $months = floor($diff / 2592000);

            return $months . ' ' . ($months === 1 ? 'month' : 'months') . ' ago';
        }

        $years = floor($diff / 31536000);

        return $years . ' ' . ($years === 1 ? 'year' : 'years') . ' ago';
    }

    /**
     * Check if timestamp is in the past.
     *
     * @param int $timestamp Unix timestamp
     * @return bool True if in the past
     */
    public static function isPast(int $timestamp): bool
    {
        return $timestamp < time();
    }

    /**
     * Check if timestamp is in the future.
     *
     * @param int $timestamp Unix timestamp
     * @return bool True if in the future
     */
    public static function isFuture(int $timestamp): bool
    {
        return $timestamp > time();
    }
}
