<?php

declare(strict_types=1);

namespace ClaudeAgents\Contracts;

/**
 * Interface for document storage.
 */
interface DocumentStoreInterface
{
    /**
     * Add a document to the store.
     *
     * @param string $id Unique document identifier
     * @param string $title Document title
     * @param string $content Document content
     * @param array<string, mixed> $metadata Optional metadata
     */
    public function add(string $id, string $title, string $content, array $metadata = []): void;

    /**
     * Get a document by ID.
     *
     * @return array<string, mixed>|null
     */
    public function get(string $id): ?array;

    /**
     * Check if document exists.
     */
    public function has(string $id): bool;

    /**
     * Remove a document.
     */
    public function remove(string $id): void;

    /**
     * Get all documents.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array;

    /**
     * Get document count.
     */
    public function count(): int;

    /**
     * Clear all documents.
     */
    public function clear(): void;
}
