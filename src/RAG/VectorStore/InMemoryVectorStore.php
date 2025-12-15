<?php

declare(strict_types=1);

namespace ClaudeAgents\RAG\VectorStore;

/**
 * In-memory vector store with cosine similarity search.
 *
 * Good for development and small datasets.
 * Not suitable for production with large datasets.
 */
class InMemoryVectorStore implements VectorStoreInterface
{
    /**
     * @var array<string, array{id: string, text: string, embedding: array<float>, metadata: array<string, mixed>}>
     */
    private array $documents = [];

    public function add(array $documents): void
    {
        foreach ($documents as $doc) {
            $this->documents[$doc['id']] = $doc;
        }
    }

    public function search(array $queryEmbedding, int $topK = 5, array $filters = []): array
    {
        if (empty($this->documents)) {
            return [];
        }

        // Apply filters
        $filtered = array_filter(
            $this->documents,
            fn ($doc) => $this->matchesFilters($doc['metadata'] ?? [], $filters)
        );

        if (empty($filtered)) {
            return [];
        }

        // Calculate similarities
        $scored = [];
        foreach ($filtered as $doc) {
            $similarity = $this->cosineSimilarity($queryEmbedding, $doc['embedding']);

            $scored[] = [
                'id' => $doc['id'],
                'text' => $doc['text'],
                'score' => $similarity,
                'metadata' => $doc['metadata'] ?? [],
            ];
        }

        // Sort by score descending
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        // Return top K
        return array_slice($scored, 0, $topK);
    }

    public function delete(array $ids): void
    {
        foreach ($ids as $id) {
            unset($this->documents[$id]);
        }
    }

    public function clear(): void
    {
        $this->documents = [];
    }

    public function count(): int
    {
        return count($this->documents);
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
     * Check if metadata matches filters.
     *
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $filters
     */
    private function matchesFilters(array $metadata, array $filters): bool
    {
        if (empty($filters)) {
            return true;
        }

        foreach ($filters as $key => $value) {
            if (! isset($metadata[$key])) {
                return false;
            }

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
    }
}
