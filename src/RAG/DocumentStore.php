<?php

declare(strict_types=1);

namespace ClaudeAgents\RAG;

use ClaudeAgents\Contracts\DocumentStoreInterface;

/**
 * In-memory document store.
 */
class DocumentStore implements DocumentStoreInterface
{
    /**
     * @var array<string, Document>
     */
    private array $documents = [];

    public function add(string $id, string $title, string $content, array $metadata = []): void
    {
        $this->documents[$id] = new Document($id, $title, $content, $metadata);
    }

    public function get(string $id): ?array
    {
        if (! isset($this->documents[$id])) {
            return null;
        }

        return $this->documents[$id]->toArray();
    }

    public function has(string $id): bool
    {
        return isset($this->documents[$id]);
    }

    public function remove(string $id): void
    {
        unset($this->documents[$id]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $result = [];
        foreach ($this->documents as $id => $doc) {
            $result[$id] = $doc->toArray();
        }

        return $result;
    }

    public function count(): int
    {
        return count($this->documents);
    }

    public function clear(): void
    {
        $this->documents = [];
    }

    /**
     * Get a document object directly.
     */
    public function getDocument(string $id): ?Document
    {
        return $this->documents[$id] ?? null;
    }

    /**
     * Get all document objects.
     *
     * @return array<string, Document>
     */
    public function getDocuments(): array
    {
        return $this->documents;
    }
}
