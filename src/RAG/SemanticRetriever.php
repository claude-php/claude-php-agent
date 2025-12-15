<?php

declare(strict_types=1);

namespace ClaudeAgents\RAG;

use ClaudeAgents\Contracts\RetrieverInterface;

/**
 * Semantic document retrieval (embedding-based).
 *
 * This is a placeholder for semantic retrieval. In production,
 * you would integrate with an embedding service like OpenAI, Cohere, or local embeddings.
 *
 * For now, it falls back to keyword matching.
 */
class SemanticRetriever implements RetrieverInterface
{
    /**
     * @var array<array<string, mixed>>
     */
    private array $chunks = [];

    /**
     * @var callable|null
     */
    private $embeddingFunction = null;

    /**
     * @param callable|null $embeddingFunction Function to generate embeddings
     */
    public function __construct(?callable $embeddingFunction = null)
    {
        $this->embeddingFunction = $embeddingFunction;
    }

    public function setChunks(array $chunks): void
    {
        $this->chunks = $chunks;
    }

    public function retrieve(string $query, int $topK = 3, array $filters = []): array
    {
        if (empty($this->chunks)) {
            return [];
        }

        // Apply metadata filters first
        $filteredChunks = $this->applyFilters($this->chunks, $filters);

        if (empty($filteredChunks)) {
            return [];
        }

        // If no embedding function provided, fall back to keyword matching
        if ($this->embeddingFunction === null) {
            return $this->fallbackToKeywordMatching($query, $topK, $filteredChunks);
        }

        try {
            // Get query embedding
            $queryEmbedding = ($this->embeddingFunction)($query);

            $scored = [];

            foreach ($filteredChunks as $chunk) {
                $chunkText = $chunk['text'] ?? '';

                // Get chunk embedding
                $chunkEmbedding = ($this->embeddingFunction)($chunkText);

                // Calculate cosine similarity
                $similarity = $this->cosineSimilarity($queryEmbedding, $chunkEmbedding);

                $scored[] = [
                    'chunk' => $chunk,
                    'score' => $similarity,
                ];
            }

            // Sort by similarity descending
            usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

            // Return top K chunks
            return array_slice(
                array_map(fn ($x) => $x['chunk'], $scored),
                0,
                $topK
            );
        } catch (\Throwable $e) {
            // Fallback to keyword matching if embedding fails
            return $this->fallbackToKeywordMatching($query, $topK, $filteredChunks);
        }
    }

    /**
     * Calculate cosine similarity between two vectors.
     *
     * @param array<float> $a First vector
     * @param array<float> $b Second vector
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        if (count($a) !== count($b) || count($a) === 0) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < count($a); $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        $normA = sqrt($normA);
        $normB = sqrt($normB);

        if ($normA === 0.0 || $normB === 0.0) {
            return 0.0;
        }

        return $dotProduct / ($normA * $normB);
    }

    /**
     * Fallback to keyword-based retrieval.
     *
     * @param int $topK Number of results
     * @param array<array<string, mixed>> $chunks Chunks to search
     * @return array<array<string, mixed>>
     */
    private function fallbackToKeywordMatching(string $query, int $topK, array $chunks): array
    {
        $queryLower = strtolower($query);
        $queryWords = array_filter(
            preg_split('/\W+/', $queryLower, -1, PREG_SPLIT_NO_EMPTY),
            fn ($w) => strlen($w) > 2
        );

        if (empty($queryWords)) {
            return [];
        }

        $scored = [];

        foreach ($chunks as $chunk) {
            $chunkText = $chunk['text'] ?? '';
            $chunkLower = strtolower($chunkText);

            $score = 0;

            foreach ($queryWords as $word) {
                $count = substr_count($chunkLower, $word);
                $score += $count * strlen($word);
            }

            if (strpos($chunkLower, $queryLower) !== false) {
                $score += 50;
            }

            $scored[] = [
                'chunk' => $chunk,
                'score' => $score,
            ];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice(
            array_map(fn ($x) => $x['chunk'], $scored),
            0,
            $topK
        );
    }

    /**
     * Apply metadata filters to chunks.
     *
     * @param array<array<string, mixed>> $chunks
     * @param array<string, mixed> $filters
     * @return array<array<string, mixed>>
     */
    private function applyFilters(array $chunks, array $filters): array
    {
        if (empty($filters)) {
            return $chunks;
        }

        return array_filter($chunks, function ($chunk) use ($filters) {
            $metadata = $chunk['metadata'] ?? [];

            foreach ($filters as $key => $value) {
                if (! isset($metadata[$key])) {
                    return false;
                }

                // Handle array values (OR logic)
                if (is_array($value)) {
                    if (! in_array($metadata[$key], $value, true)) {
                        return false;
                    }
                } else {
                    if ($metadata[$key] !== $value) {
                        return false;
                    }
                }
            }

            return true;
        });
    }
}
