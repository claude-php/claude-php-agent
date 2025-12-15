<?php

declare(strict_types=1);

namespace ClaudeAgents\Observability;

/**
 * System health check for monitoring agent and dependency status.
 */
class HealthCheck
{
    public const STATUS_HEALTHY = 'healthy';
    public const STATUS_DEGRADED = 'degraded';
    public const STATUS_UNHEALTHY = 'unhealthy';

    /**
     * @var array<string, callable> Health check functions
     */
    private array $checks = [];

    /**
     * @var array<string, array{status: string, message: string, duration_ms: float, timestamp: float}> Cached results
     */
    private array $cachedResults = [];

    /**
     * @var int Cache TTL in seconds
     */
    private int $cacheTtl = 60;

    public function __construct(int $cacheTtl = 60)
    {
        $this->cacheTtl = $cacheTtl;
    }

    /**
     * Register a health check.
     *
     * Check function should return array{status: string, message: string}
     *
     * @param string $name Check name
     * @param callable $check Check function: fn(): array{status: string, message: string}
     */
    public function registerCheck(string $name, callable $check): self
    {
        $this->checks[$name] = $check;

        return $this;
    }

    /**
     * Run all health checks.
     *
     * @param bool $useCache Whether to use cached results
     * @return array<string, mixed>
     */
    public function check(bool $useCache = true): array
    {
        $results = [];
        $overallStatus = self::STATUS_HEALTHY;

        foreach ($this->checks as $name => $check) {
            // Use cache if available and valid
            if ($useCache && $this->isCacheValid($name)) {
                $results[$name] = $this->cachedResults[$name];
            } else {
                $results[$name] = $this->runCheck($name, $check);
            }

            // Update overall status
            if ($results[$name]['status'] === self::STATUS_UNHEALTHY) {
                $overallStatus = self::STATUS_UNHEALTHY;
            } elseif ($results[$name]['status'] === self::STATUS_DEGRADED && $overallStatus !== self::STATUS_UNHEALTHY) {
                $overallStatus = self::STATUS_DEGRADED;
            }
        }

        return [
            'status' => $overallStatus,
            'timestamp' => microtime(true),
            'checks' => $results,
        ];
    }

    /**
     * Run a single check.
     *
     * @return array{status: string, message: string, duration_ms: float, timestamp: float}
     */
    private function runCheck(string $name, callable $check): array
    {
        $startTime = microtime(true);

        try {
            $result = $check();

            if (! isset($result['status']) || ! isset($result['message'])) {
                throw new \RuntimeException('Check must return array with status and message');
            }

            $status = $result['status'];
            $message = $result['message'];
        } catch (\Throwable $e) {
            $status = self::STATUS_UNHEALTHY;
            $message = "Check failed: {$e->getMessage()}";
        }

        $duration = (microtime(true) - $startTime) * 1000;

        $result = [
            'status' => $status,
            'message' => $message,
            'duration_ms' => $duration,
            'timestamp' => microtime(true),
        ];

        // Cache result
        $this->cachedResults[$name] = $result;

        return $result;
    }

    /**
     * Check if cached result is valid.
     */
    private function isCacheValid(string $name): bool
    {
        if (! isset($this->cachedResults[$name])) {
            return false;
        }

        $age = microtime(true) - $this->cachedResults[$name]['timestamp'];

        return $age < $this->cacheTtl;
    }

    /**
     * Clear cache for a specific check or all checks.
     */
    public function clearCache(?string $name = null): void
    {
        if ($name === null) {
            $this->cachedResults = [];
        } else {
            unset($this->cachedResults[$name]);
        }
    }

    /**
     * Get list of registered checks.
     *
     * @return array<string>
     */
    public function getRegisteredChecks(): array
    {
        return array_keys($this->checks);
    }

    /**
     * Check if system is healthy.
     */
    public function isHealthy(): bool
    {
        $result = $this->check();

        return $result['status'] === self::STATUS_HEALTHY;
    }

    /**
     * Create default health checks.
     */
    public static function createDefault(): self
    {
        $health = new self();

        // Check PHP memory
        $health->registerCheck('php_memory', function () {
            $memoryUsage = memory_get_usage(true);
            $memoryLimit = ini_get('memory_limit');

            if ($memoryLimit === '-1') {
                return [
                    'status' => self::STATUS_HEALTHY,
                    'message' => 'Memory: ' . self::formatBytes($memoryUsage) . ' (unlimited)',
                ];
            }

            $memoryLimitBytes = self::parseMemoryLimit($memoryLimit);
            $usagePercent = ($memoryUsage / $memoryLimitBytes) * 100;

            if ($usagePercent >= 90) {
                return [
                    'status' => self::STATUS_UNHEALTHY,
                    'message' => sprintf('Memory usage critical: %.1f%%', $usagePercent),
                ];
            }

            if ($usagePercent >= 75) {
                return [
                    'status' => self::STATUS_DEGRADED,
                    'message' => sprintf('Memory usage high: %.1f%%', $usagePercent),
                ];
            }

            return [
                'status' => self::STATUS_HEALTHY,
                'message' => sprintf('Memory usage: %.1f%%', $usagePercent),
            ];
        });

        // Check disk space
        $health->registerCheck('disk_space', function () {
            $free = disk_free_space('.');
            $total = disk_total_space('.');

            if ($free === false || $total === false) {
                return [
                    'status' => self::STATUS_DEGRADED,
                    'message' => 'Could not determine disk space',
                ];
            }

            $usagePercent = (($total - $free) / $total) * 100;

            if ($usagePercent >= 95) {
                return [
                    'status' => self::STATUS_UNHEALTHY,
                    'message' => sprintf('Disk space critical: %.1f%% used', $usagePercent),
                ];
            }

            if ($usagePercent >= 85) {
                return [
                    'status' => self::STATUS_DEGRADED,
                    'message' => sprintf('Disk space low: %.1f%% used', $usagePercent),
                ];
            }

            return [
                'status' => self::STATUS_HEALTHY,
                'message' => sprintf('Disk space: %.1f%% used', $usagePercent),
            ];
        });

        // Check PHP version
        $health->registerCheck('php_version', function () {
            $version = PHP_VERSION;
            $majorMinor = (float)PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;

            if ($majorMinor < 8.1) {
                return [
                    'status' => self::STATUS_UNHEALTHY,
                    'message' => "PHP version {$version} is unsupported (requires 8.1+)",
                ];
            }

            return [
                'status' => self::STATUS_HEALTHY,
                'message' => "PHP version {$version}",
            ];
        });

        return $health;
    }

    /**
     * Parse PHP memory limit string to bytes.
     */
    private static function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        $unit = strtolower($limit[strlen($limit) - 1]);
        $value = (int)$limit;

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }

    /**
     * Format bytes to human-readable string.
     */
    private static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $index = 0;

        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }

        return round($bytes, 2) . ' ' . $units[$index];
    }
}
