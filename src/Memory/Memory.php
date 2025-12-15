<?php

declare(strict_types=1);

namespace ClaudeAgents\Memory;

use ClaudeAgents\Contracts\MemoryInterface;

/**
 * In-memory state storage.
 *
 * Simple key-value store that persists for the lifetime of the agent.
 * For persistent storage across sessions, use FileMemory.
 */
class Memory implements MemoryInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    /**
     * @var array<array{action: string, key: string, value: mixed, timestamp: int}>
     */
    private array $history = [];

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
        $this->history[] = [
            'action' => 'set',
            'key' => $key,
            'value' => $value,
            'timestamp' => time(),
        ];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function forget(string $key): void
    {
        if ($this->has($key)) {
            $this->history[] = [
                'action' => 'forget',
                'key' => $key,
                'value' => $this->data[$key],
                'timestamp' => time(),
            ];
            unset($this->data[$key]);
        }
    }

    public function all(): array
    {
        return $this->data;
    }

    public function clear(): void
    {
        $this->history[] = [
            'action' => 'clear',
            'key' => '*',
            'value' => $this->data,
            'timestamp' => time(),
        ];
        $this->data = [];
    }

    /**
     * Get the history of all memory operations.
     *
     * @return array<array{action: string, key: string, value: mixed, timestamp: int}>
     */
    public function getHistory(): array
    {
        return $this->history;
    }

    /**
     * Increment a numeric value.
     */
    public function increment(string $key, int $amount = 1): int
    {
        $current = (int) ($this->data[$key] ?? 0);
        $new = $current + $amount;
        $this->set($key, $new);

        return $new;
    }

    /**
     * Decrement a numeric value.
     */
    public function decrement(string $key, int $amount = 1): int
    {
        return $this->increment($key, -$amount);
    }

    /**
     * Push a value onto an array.
     */
    public function push(string $key, mixed $value): void
    {
        $array = $this->get($key, []);
        if (! is_array($array)) {
            $array = [$array];
        }
        $array[] = $value;
        $this->set($key, $array);
    }

    /**
     * Get and remove a value.
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);

        return $value;
    }
}
