<?php

declare(strict_types=1);

namespace ClaudeAgents\Contracts;

/**
 * Interface for embedding generation.
 *
 * Embeddings are vector representations of text used for semantic search,
 * similarity comparison, and RAG (Retrieval-Augmented Generation).
 */
interface EmbeddingInterface
{
    /**
     * Generate an embedding for a single text.
     *
     * @param string $text The text to embed
     * @return array<float> The embedding vector
     */
    public function embed(string $text): array;

    /**
     * Generate embeddings for multiple texts.
     *
     * @param array<string> $texts Array of texts to embed
     * @return array<array<float>> Array of embedding vectors
     */
    public function embedBatch(array $texts): array;

    /**
     * Calculate cosine similarity between two embeddings.
     *
     * @param array<float> $embedding1 First embedding vector
     * @param array<float> $embedding2 Second embedding vector
     * @return float Similarity score between -1 and 1
     */
    public function similarity(array $embedding1, array $embedding2): float;

    /**
     * Get the dimension of embeddings produced by this model.
     */
    public function getDimension(): int;

    /**
     * Get the model identifier.
     */
    public function getModel(): string;
}
