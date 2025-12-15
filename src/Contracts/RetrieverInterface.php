<?php

declare(strict_types=1);

namespace ClaudeAgents\Contracts;

/**
 * Interface for document retrieval strategies.
 */
interface RetrieverInterface
{
    /**
     * Retrieve relevant documents for a query.
     *
     * @param string $query The search query
     * @param int $topK Number of results to return
     * @param array<string, mixed> $filters Metadata filters to apply
     * @return array<array<string, mixed>> Array of retrieved documents/chunks
     */
    public function retrieve(string $query, int $topK = 3, array $filters = []): array;

    /**
     * Set the document store to retrieve from.
     *
     * @param array<array<string, mixed>> $chunks Array of chunks to search
     */
    public function setChunks(array $chunks): void;
}
