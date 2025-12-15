<?php

declare(strict_types=1);

namespace ClaudeAgents\RAG\Storage;

use ClaudeAgents\Contracts\DocumentStoreInterface;
use ClaudeAgents\RAG\Document;

/**
 * File-based persistent document store.
 *
 * Stores documents as JSON files in a directory.
 */
class FileDocumentStore implements DocumentStoreInterface
{
    /**
     * @param string $storagePath Directory to store documents
     */
    public function __construct(
        private readonly string $storagePath,
    ) {
        if (! is_dir($this->storagePath)) {
            if (! mkdir($this->storagePath, 0o755, true)) {
                throw new \RuntimeException("Failed to create storage directory: {$this->storagePath}");
            }
        }
    }

    public function add(string $id, string $title, string $content, array $metadata = []): void
    {
        $document = new Document($id, $title, $content, $metadata);
        $filePath = $this->getFilePath($id);

        $data = json_encode($document->toArray(), JSON_PRETTY_PRINT);
        if ($data === false) {
            throw new \RuntimeException('Failed to encode document');
        }

        if (file_put_contents($filePath, $data) === false) {
            throw new \RuntimeException("Failed to write document to file: {$filePath}");
        }
    }

    public function get(string $id): ?array
    {
        $filePath = $this->getFilePath($id);

        if (! file_exists($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException("Failed to read document file: {$filePath}");
        }

        $data = json_decode($content, true);
        if ($data === null) {
            throw new \RuntimeException('Failed to decode document: ' . json_last_error_msg());
        }

        return $data;
    }

    public function has(string $id): bool
    {
        return file_exists($this->getFilePath($id));
    }

    public function remove(string $id): void
    {
        $filePath = $this->getFilePath($id);

        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    public function all(): array
    {
        $documents = [];
        $files = glob($this->storagePath . '/*.json');

        if ($files === false) {
            return [];
        }

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            $data = json_decode($content, true);
            if ($data === null || ! isset($data['id'])) {
                continue;
            }

            $documents[$data['id']] = $data;
        }

        return $documents;
    }

    public function count(): int
    {
        $files = glob($this->storagePath . '/*.json');

        return $files === false ? 0 : count($files);
    }

    public function clear(): void
    {
        $files = glob($this->storagePath . '/*.json');

        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            unlink($file);
        }
    }

    /**
     * Get file path for document ID.
     */
    private function getFilePath(string $id): string
    {
        // Sanitize ID for filename
        $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $id);

        return $this->storagePath . '/' . $safeId . '.json';
    }
}
