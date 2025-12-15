<?php

declare(strict_types=1);

namespace ClaudeAgents\Memory;

use ClaudeAgents\Contracts\MemoryInterface;

/**
 * File-based persistent memory storage.
 *
 * Stores state in a JSON file that persists across sessions.
 * Useful for autonomous agents that need to maintain state
 * between executions.
 */
class FileMemory implements MemoryInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    private string $filePath;
    private bool $autoSave;

    /**
     * @param string $filePath Path to the JSON file
     * @param bool $autoSave Whether to save after each modification
     */
    public function __construct(string $filePath, bool $autoSave = true)
    {
        $this->filePath = $filePath;
        $this->autoSave = $autoSave;
        $this->load();
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
        if ($this->autoSave) {
            $this->save();
        }
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
        unset($this->data[$key]);
        if ($this->autoSave) {
            $this->save();
        }
    }

    public function all(): array
    {
        return $this->data;
    }

    public function clear(): void
    {
        $this->data = [];
        if ($this->autoSave) {
            $this->save();
        }
    }

    /**
     * Load data from the file.
     */
    public function load(): void
    {
        if (file_exists($this->filePath)) {
            $contents = file_get_contents($this->filePath);
            if ($contents !== false) {
                $decoded = json_decode($contents, true);
                if (is_array($decoded)) {
                    $this->data = $decoded;
                }
            }
        }
    }

    /**
     * Save data to the file.
     */
    public function save(): void
    {
        $dir = dirname($this->filePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        file_put_contents(
            $this->filePath,
            json_encode($this->data, JSON_PRETTY_PRINT)
        );
    }

    /**
     * Get the file path.
     */
    public function getFilePath(): string
    {
        return $this->filePath;
    }

    /**
     * Delete the storage file.
     */
    public function delete(): bool
    {
        if (file_exists($this->filePath)) {
            return unlink($this->filePath);
        }

        return true;
    }

    /**
     * Check if the storage file exists.
     */
    public function exists(): bool
    {
        return file_exists($this->filePath);
    }

    /**
     * Get the last modified time of the storage file.
     */
    public function getLastModified(): ?int
    {
        if (file_exists($this->filePath)) {
            return filemtime($this->filePath) ?: null;
        }

        return null;
    }
}
