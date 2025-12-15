<?php

declare(strict_types=1);

namespace ClaudeAgents\RAG\Reranking;

/**
 * Interface for document re-ranking.
 *
 * Re-rankers take initial retrieval results and reorder them
 * for better relevance to the query.
 */
interface RerankerInterface
{
    /**
     * Re-rank documents based on query.
     *
     * @param string $query The search query
     * @param array<array<string, mixed>> $documents Retrieved documents to re-rank
     * @param int $topK Number of top results to return
     * @return array<array<string, mixed>> Re-ranked documents
     */
    public function rerank(string $query, array $documents, int $topK): array;
}
