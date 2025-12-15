<?php

declare(strict_types=1);

namespace ClaudeAgents\RAG\VectorStore;

/**
 * Interface for vector database operations.
 *
 * Provides semantic search capabilities using embeddings.
 */
interface VectorStoreInterface
{
    /**
     * Add documents with their embeddings.
     *
     * @param array<array{id: string, text: string, embedding: array<float>, metadata: array<string, mixed>}> $documents
     */
    public function add(array $documents): void;

    /**
     * Search for similar documents using vector similarity.
     *
     * @param array<float> $queryEmbedding Query embedding vector
     * @param int $topK Number of results to return
     * @param array<string, mixed> $filters Metadata filters
     * @return array<array{id: string, text: string, score: float, metadata: array<string, mixed>}>
     */
    public function search(array $queryEmbedding, int $topK = 5, array $filters = []): array;

    /**
     * Delete documents by ID.
     *
     * @param array<string> $ids
     */
    public function delete(array $ids): void;

    /**
     * Clear all documents.
     */
    public function clear(): void;

    /**
     * Get document count.
     */
    public function count(): int;
}
