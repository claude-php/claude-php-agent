<?php

declare(strict_types=1);

namespace ClaudeAgents\Services\Storage;

use ClaudeAgents\Services\ServiceInterface;

/**
 * Abstract storage service for file operations.
 *
 * Provides a consistent interface for storing and retrieving files
 * with support for multiple backends (local, S3, etc.).
 */
abstract class StorageService implements ServiceInterface
{
    protected bool $ready = false;

    public function getName(): string
    {
        return 'storage';
    }

    public function isReady(): bool
    {
        return $this->ready;
    }

    public function getSchema(): array
    {
        return [
            'name' => $this->getName(),
            'ready' => $this->ready,
            'methods' => [
                'saveFile' => [
                    'parameters' => ['flowId' => 'string', 'fileName' => 'string', 'data' => 'string'],
                    'return' => 'void',
                    'description' => 'Save a file to storage',
                ],
                'getFile' => [
                    'parameters' => ['flowId' => 'string', 'fileName' => 'string'],
                    'return' => 'string',
                    'description' => 'Get a file from storage',
                ],
                'listFiles' => [
                    'parameters' => ['flowId' => 'string'],
                    'return' => 'array',
                    'description' => 'List all files for a flow',
                ],
                'deleteFile' => [
                    'parameters' => ['flowId' => 'string', 'fileName' => 'string'],
                    'return' => 'void',
                    'description' => 'Delete a file from storage',
                ],
                'fileExists' => [
                    'parameters' => ['flowId' => 'string', 'fileName' => 'string'],
                    'return' => 'bool',
                    'description' => 'Check if a file exists',
                ],
            ],
        ];
    }

    /**
     * Build the full path for a file.
     *
     * @param string $flowId Flow/user identifier for namespacing
     * @param string $fileName File name
     * @return string Full file path
     */
    abstract public function buildPath(string $flowId, string $fileName): string;

    /**
     * Parse a full path to extract flow ID and file name.
     *
     * @param string $fullPath Full file path
     * @return array{flowId: string, fileName: string}
     * @throws \InvalidArgumentException If path format is invalid
     */
    abstract public function parsePath(string $fullPath): array;

    /**
     * Save a file to storage.
     *
     * @param string $flowId Flow/user identifier
     * @param string $fileName File name
     * @param string $data File content
     * @return void
     * @throws \RuntimeException If save fails
     */
    abstract public function saveFile(string $flowId, string $fileName, string $data): void;

    /**
     * Get a file from storage.
     *
     * @param string $flowId Flow/user identifier
     * @param string $fileName File name
     * @return string File content
     * @throws \RuntimeException If file not found or read fails
     */
    abstract public function getFile(string $flowId, string $fileName): string;

    /**
     * List all files for a flow.
     *
     * @param string $flowId Flow/user identifier
     * @return array<string> Array of file names
     */
    abstract public function listFiles(string $flowId): array;

    /**
     * Delete a file from storage.
     *
     * @param string $flowId Flow/user identifier
     * @param string $fileName File name
     * @return void
     * @throws \RuntimeException If delete fails
     */
    abstract public function deleteFile(string $flowId, string $fileName): void;

    /**
     * Check if a file exists.
     *
     * @param string $flowId Flow/user identifier
     * @param string $fileName File name
     * @return bool True if file exists
     */
    abstract public function fileExists(string $flowId, string $fileName): bool;

    /**
     * Get the size of a file in bytes.
     *
     * @param string $flowId Flow/user identifier
     * @param string $fileName File name
     * @return int File size in bytes
     * @throws \RuntimeException If file not found
     */
    abstract public function getFileSize(string $flowId, string $fileName): int;
}
