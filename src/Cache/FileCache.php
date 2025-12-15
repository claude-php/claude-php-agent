<?php

declare(strict_types=1);

namespace ClaudeAgents\Cache;

use ClaudeAgents\Contracts\CacheInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * File-based cache implementation.
 */
class FileCache implements CacheInterface
{
    private LoggerInterface $logger;

    /**
     * @param string $directory Cache directory
     */
    public function __construct(
        private readonly string $directory,
        array $options = [],
    ) {
        $this->logger = $options['logger'] ?? new NullLogger();

        // Create directory if it doesn't exist
        if (! file_exists($directory)) {
            mkdir($directory, 0o755, true);
        }
    }

    public function get(string $key): mixed
    {
        $file = $this->getFilePath($key);

        if (! file_exists($file)) {
            return null;
        }

        try {
            $contents = file_get_contents($file);
            if ($contents === false) {
                return null;
            }

            $data = json_decode($contents, true);
            if (! is_array($data)) {
                return null;
            }

            // Check expiration
            if (isset($data['expires']) && $data['expires'] > 0 && time() > $data['expires']) {
                $this->delete($key);

                return null;
            }

            return $data['value'] ?? null;
        } catch (\Throwable $e) {
            $this->logger->warning("Cache read error: {$e->getMessage()}");

            return null;
        }
    }

    public function set(string $key, mixed $value, int $ttl = 0): void
    {
        $file = $this->getFilePath($key);
        $expires = $ttl > 0 ? time() + $ttl : 0;

        $data = [
            'value' => $value,
            'expires' => $expires,
            'created' => time(),
        ];

        try {
            file_put_contents($file, json_encode($data));
        } catch (\Throwable $e) {
            $this->logger->warning("Cache write error: {$e->getMessage()}");
        }
    }

    public function delete(string $key): void
    {
        $file = $this->getFilePath($key);

        if (file_exists($file)) {
            try {
                unlink($file);
            } catch (\Throwable $e) {
                $this->logger->warning("Cache delete error: {$e->getMessage()}");
            }
        }
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function clear(): void
    {
        try {
            $files = glob($this->directory . '/*.cache');
            if ($files !== false) {
                foreach ($files as $file) {
                    unlink($file);
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning("Cache clear error: {$e->getMessage()}");
        }
    }

    /**
     * Get the file path for a cache key.
     */
    private function getFilePath(string $key): string
    {
        $hash = md5($key);

        return $this->directory . '/' . $hash . '.cache';
    }
}
