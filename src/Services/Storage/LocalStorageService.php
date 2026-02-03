<?php

declare(strict_types=1);

namespace ClaudeAgents\Services\Storage;

use ClaudeAgents\Services\Settings\SettingsService;

/**
 * Local file system storage implementation.
 *
 * Stores files in a directory structure organized by flow ID.
 */
class LocalStorageService extends StorageService
{
    private string $baseDirectory;

    /**
     * @param SettingsService $settings Settings service for configuration
     * @param string|null $baseDirectory Optional base directory override
     */
    public function __construct(
        SettingsService $settings,
        ?string $baseDirectory = null
    ) {
        $this->baseDirectory = $baseDirectory ?? $settings->get('storage.directory', './storage');
    }

    public function initialize(): void
    {
        if ($this->ready) {
            return;
        }

        // Ensure base directory exists
        if (! file_exists($this->baseDirectory)) {
            if (! mkdir($this->baseDirectory, 0755, true) && ! is_dir($this->baseDirectory)) {
                throw new \RuntimeException("Failed to create storage directory: {$this->baseDirectory}");
            }
        }

        if (! is_writable($this->baseDirectory)) {
            throw new \RuntimeException("Storage directory is not writable: {$this->baseDirectory}");
        }

        $this->ready = true;
    }

    public function teardown(): void
    {
        $this->ready = false;
    }

    public function buildPath(string $flowId, string $fileName): string
    {
        // Sanitize flow ID and file name
        $flowId = $this->sanitizePath($flowId);
        $fileName = $this->sanitizePath($fileName);

        return $this->baseDirectory . DIRECTORY_SEPARATOR . $flowId . DIRECTORY_SEPARATOR . $fileName;
    }

    public function parsePath(string $fullPath): array
    {
        // Remove base directory from path
        if (! str_starts_with($fullPath, $this->baseDirectory)) {
            throw new \InvalidArgumentException('Path does not start with base directory');
        }

        $relativePath = substr($fullPath, strlen($this->baseDirectory) + 1);
        $parts = explode(DIRECTORY_SEPARATOR, $relativePath);

        if (count($parts) < 2) {
            throw new \InvalidArgumentException('Invalid path format');
        }

        return [
            'flowId' => $parts[0],
            'fileName' => implode(DIRECTORY_SEPARATOR, array_slice($parts, 1)),
        ];
    }

    public function saveFile(string $flowId, string $fileName, string $data): void
    {
        $path = $this->buildPath($flowId, $fileName);
        $directory = dirname($path);

        // Ensure directory exists
        if (! file_exists($directory)) {
            if (! mkdir($directory, 0755, true) && ! is_dir($directory)) {
                throw new \RuntimeException("Failed to create directory: {$directory}");
            }
        }

        // Write file atomically
        $tempFile = $path . '.tmp.' . uniqid();
        if (file_put_contents($tempFile, $data) === false) {
            throw new \RuntimeException("Failed to write file: {$path}");
        }

        if (! rename($tempFile, $path)) {
            @unlink($tempFile);
            throw new \RuntimeException("Failed to move temporary file to: {$path}");
        }
    }

    public function getFile(string $flowId, string $fileName): string
    {
        $path = $this->buildPath($flowId, $fileName);

        if (! file_exists($path)) {
            throw new \RuntimeException("File not found: {$path}");
        }

        $content = file_get_contents($path);

        if ($content === false) {
            throw new \RuntimeException("Failed to read file: {$path}");
        }

        return $content;
    }

    public function listFiles(string $flowId): array
    {
        $flowDirectory = $this->baseDirectory . DIRECTORY_SEPARATOR . $this->sanitizePath($flowId);

        if (! file_exists($flowDirectory)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($flowDirectory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relativePath = substr($file->getPathname(), strlen($flowDirectory) + 1);
                $files[] = $relativePath;
            }
        }

        return $files;
    }

    public function deleteFile(string $flowId, string $fileName): void
    {
        $path = $this->buildPath($flowId, $fileName);

        if (! file_exists($path)) {
            return; // Already deleted
        }

        if (! unlink($path)) {
            throw new \RuntimeException("Failed to delete file: {$path}");
        }

        // Clean up empty directories
        $this->cleanupEmptyDirectories(dirname($path));
    }

    public function fileExists(string $flowId, string $fileName): bool
    {
        return file_exists($this->buildPath($flowId, $fileName));
    }

    public function getFileSize(string $flowId, string $fileName): int
    {
        $path = $this->buildPath($flowId, $fileName);

        if (! file_exists($path)) {
            throw new \RuntimeException("File not found: {$path}");
        }

        $size = filesize($path);

        if ($size === false) {
            throw new \RuntimeException("Failed to get file size: {$path}");
        }

        return $size;
    }

    /**
     * Get the base directory.
     *
     * @return string
     */
    public function getBaseDirectory(): string
    {
        return $this->baseDirectory;
    }

    /**
     * Sanitize a path component to prevent directory traversal.
     *
     * @param string $path Path component
     * @return string Sanitized path
     */
    private function sanitizePath(string $path): string
    {
        // Remove any directory traversal attempts
        $path = str_replace(['..', '\\'], ['', '/'], $path);

        // Remove leading/trailing slashes
        return trim($path, '/');
    }

    /**
     * Clean up empty directories up to the base directory.
     *
     * @param string $directory Directory to clean up
     * @return void
     */
    private function cleanupEmptyDirectories(string $directory): void
    {
        // Don't delete base directory
        if ($directory === $this->baseDirectory || ! str_starts_with($directory, $this->baseDirectory)) {
            return;
        }

        // Check if directory is empty
        if (! is_dir($directory)) {
            return;
        }

        $files = scandir($directory);
        if ($files === false || count($files) > 2) { // . and .. are always present
            return;
        }

        // Remove empty directory
        @rmdir($directory);

        // Recursively clean up parent
        $this->cleanupEmptyDirectories(dirname($directory));
    }
}
