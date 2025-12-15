<?php

declare(strict_types=1);

namespace ClaudeAgents\MultiAgent;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Shared memory/blackboard system for multi-agent coordination.
 *
 * Provides a shared workspace where agents can read and write information,
 * enabling indirect communication and coordination patterns.
 */
class SharedMemory
{
    private array $data = [];
    private array $metadata = [];
    private array $accessLog = [];
    private LoggerInterface $logger;
    private bool $trackAccess;

    /**
     * @param array<string, mixed> $options Configuration:
     *   - track_access: Log read/write operations (default: true)
     *   - logger: PSR-3 logger
     */
    public function __construct(array $options = [])
    {
        $this->trackAccess = $options['track_access'] ?? true;
        $this->logger = $options['logger'] ?? new NullLogger();
    }

    /**
     * Write data to shared memory.
     *
     * @param string $key Memory key
     * @param mixed $value Value to store
     * @param string $agentId Agent writing the data
     * @param array<string, mixed> $metadata Optional metadata
     */
    public function write(string $key, mixed $value, string $agentId, array $metadata = []): void
    {
        $this->data[$key] = $value;
        $this->metadata[$key] = [
            'written_by' => $agentId,
            'written_at' => microtime(true),
            'version' => ($this->metadata[$key]['version'] ?? 0) + 1,
            'metadata' => $metadata,
        ];

        if ($this->trackAccess) {
            $this->logAccess('write', $key, $agentId);
        }

        $this->logger->debug("Agent {$agentId} wrote to shared memory key: {$key}");
    }

    /**
     * Read data from shared memory.
     *
     * @param string $key Memory key
     * @param string $agentId Agent reading the data
     * @param mixed $default Default value if key doesn't exist
     * @return mixed
     */
    public function read(string $key, string $agentId, mixed $default = null): mixed
    {
        if ($this->trackAccess) {
            $this->logAccess('read', $key, $agentId);
        }

        $value = $this->data[$key] ?? $default;
        $this->logger->debug("Agent {$agentId} read from shared memory key: {$key}");

        return $value;
    }

    /**
     * Check if a key exists.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Delete a key from shared memory.
     */
    public function delete(string $key, string $agentId): bool
    {
        if (! $this->has($key)) {
            return false;
        }

        unset($this->data[$key], $this->metadata[$key]);

        if ($this->trackAccess) {
            $this->logAccess('delete', $key, $agentId);
        }

        $this->logger->debug("Agent {$agentId} deleted shared memory key: {$key}");

        return true;
    }

    /**
     * Get all keys in shared memory.
     *
     * @return array<string>
     */
    public function keys(): array
    {
        return array_keys($this->data);
    }

    /**
     * Get all data in shared memory.
     *
     * @return array<string, mixed>
     */
    public function getAll(): array
    {
        return $this->data;
    }

    /**
     * Get metadata for a key.
     *
     * @return array<string, mixed>|null
     */
    public function getMetadata(string $key): ?array
    {
        return $this->metadata[$key] ?? null;
    }

    /**
     * Clear all data in shared memory.
     */
    public function clear(): void
    {
        $this->data = [];
        $this->metadata = [];
        $this->logger->info('Shared memory cleared');
    }

    /**
     * Get access log.
     *
     * @return array<array>
     */
    public function getAccessLog(): array
    {
        return $this->accessLog;
    }

    /**
     * Clear access log.
     */
    public function clearAccessLog(): void
    {
        $this->accessLog = [];
    }

    /**
     * Get statistics about shared memory usage.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(): array
    {
        $reads = array_filter($this->accessLog, fn ($log) => $log['operation'] === 'read');
        $writes = array_filter($this->accessLog, fn ($log) => $log['operation'] === 'write');
        $deletes = array_filter($this->accessLog, fn ($log) => $log['operation'] === 'delete');

        return [
            'total_keys' => count($this->data),
            'total_operations' => count($this->accessLog),
            'reads' => count($reads),
            'writes' => count($writes),
            'deletes' => count($deletes),
            'unique_agents' => count(array_unique(array_column($this->accessLog, 'agent_id'))),
        ];
    }

    /**
     * Atomic compare-and-swap operation.
     *
     * @param string $key Memory key
     * @param mixed $expected Expected current value
     * @param mixed $new New value to set
     * @param string $agentId Agent performing the operation
     * @return bool True if swap succeeded
     */
    public function compareAndSwap(string $key, mixed $expected, mixed $new, string $agentId): bool
    {
        if (! $this->has($key)) {
            return false;
        }

        if ($this->data[$key] !== $expected) {
            return false;
        }

        $this->write($key, $new, $agentId, ['operation' => 'cas']);

        return true;
    }

    /**
     * Append to an array in shared memory.
     */
    public function append(string $key, mixed $value, string $agentId): void
    {
        $current = $this->read($key, $agentId, []);

        if (! is_array($current)) {
            $current = [$current];
        }

        $current[] = $value;
        $this->write($key, $current, $agentId, ['operation' => 'append']);
    }

    /**
     * Increment a numeric value.
     */
    public function increment(string $key, string $agentId, int|float $amount = 1): int|float
    {
        $current = $this->read($key, $agentId, 0);
        $new = $current + $amount;
        $this->write($key, $new, $agentId, ['operation' => 'increment']);

        return $new;
    }

    /**
     * Log an access operation.
     */
    private function logAccess(string $operation, string $key, string $agentId): void
    {
        $this->accessLog[] = [
            'operation' => $operation,
            'key' => $key,
            'agent_id' => $agentId,
            'timestamp' => microtime(true),
        ];
    }

    /**
     * Export shared memory state.
     *
     * @return array<string, mixed>
     */
    public function export(): array
    {
        return [
            'data' => $this->data,
            'metadata' => $this->metadata,
            'access_log' => $this->accessLog,
            'statistics' => $this->getStatistics(),
        ];
    }

    /**
     * Import shared memory state.
     *
     * @param array<string, mixed> $state
     */
    public function import(array $state): void
    {
        $this->data = $state['data'] ?? [];
        $this->metadata = $state['metadata'] ?? [];
        $this->accessLog = $state['access_log'] ?? [];
        $this->logger->info('Shared memory state imported');
    }
}
