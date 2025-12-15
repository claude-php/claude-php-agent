<?php

declare(strict_types=1);

namespace ClaudeAgents\RAG\VectorStore;

use ClaudeAgents\Contracts\RetrieverInterface;

/**
 * Retriever that uses a vector store for semantic search.
 *
 * Requires an embedding function to convert text to vectors.
 */
class VectorRetriever implements RetrieverInterface
{
    /**
     * @param VectorStoreInterface $vectorStore Vector database
     * @param callable $embeddingFunction Function that converts text to embedding vector
     */
    public function __construct(
        private readonly VectorStoreInterface $vectorStore,
        private readonly mixed $embeddingFunction,
    ) {
    }

    public function retrieve(string $query, int $topK = 3, array $filters = []): array
    {
        // Generate query embedding
        $queryEmbedding = ($this->embeddingFunction)($query);

        if (! is_array($queryEmbedding)) {
            throw new \RuntimeException('Embedding function must return array');
        }

        // Search vector store
        $results = $this->vectorStore->search($queryEmbedding, $topK, $filters);

        // Convert to chunk format expected by RAG pipeline
        return array_map(
            fn ($result) => [
                'id' => $result['id'],
                'text' => $result['text'],
                'score' => $result['score'],
                'metadata' => $result['metadata'],
            ],
            $results
        );
    }

    public function setChunks(array $chunks): void
    {
        // Clear existing documents
        $this->vectorStore->clear();

        if (empty($chunks)) {
            return;
        }

        // Generate embeddings and add to vector store
        $documents = [];

        foreach ($chunks as $chunk) {
            $text = $chunk['text'] ?? '';
            if (empty($text)) {
                continue;
            }

            $embedding = ($this->embeddingFunction)($text);

            if (! is_array($embedding)) {
                continue;
            }

            $documents[] = [
                'id' => $chunk['id'] ?? uniqid('chunk_'),
                'text' => $text,
                'embedding' => $embedding,
                'metadata' => $chunk['metadata'] ?? [],
            ];
        }

        if (! empty($documents)) {
            $this->vectorStore->add($documents);
        }
    }
}
