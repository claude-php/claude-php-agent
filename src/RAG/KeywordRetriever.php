<?php

declare(strict_types=1);

namespace ClaudeAgents\RAG;

use ClaudeAgents\Contracts\RetrieverInterface;

/**
 * Keyword-based document retrieval.
 *
 * Uses TF-IDF-inspired scoring to find relevant chunks.
 */
class KeywordRetriever implements RetrieverInterface
{
    /**
     * @var array<array<string, mixed>>
     */
    private array $chunks = [];

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

        $queryLower = strtolower($query);
        $queryWords = array_filter(
            preg_split('/\W+/', $queryLower, -1, PREG_SPLIT_NO_EMPTY),
            fn ($w) => strlen($w) > 2
        );

        if (empty($queryWords)) {
            return [];
        }

        $scored = [];

        foreach ($filteredChunks as $chunk) {
            $chunkText = $chunk['text'] ?? '';
            $chunkLower = strtolower($chunkText);

            $score = 0;

            // Score based on keyword matches
            foreach ($queryWords as $word) {
                $count = substr_count($chunkLower, $word);
                $score += $count * strlen($word); // Weight by word length
            }

            // Bonus for exact phrase match
            if (strpos($chunkLower, $queryLower) !== false) {
                $score += 50;
            }

            $scored[] = [
                'chunk' => $chunk,
                'score' => $score,
            ];
        }

        // Sort by score descending
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        // Return top K chunks
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
