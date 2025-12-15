<?php

declare(strict_types=1);

namespace ClaudeAgents\Config;

use ClaudeAgents\Exceptions\ConfigurationException;

/**
 * Configuration for RAG (Retrieval-Augmented Generation) settings.
 */
class RAGConfig
{
    public const DEFAULT_CHUNK_SIZE = 512;
    public const DEFAULT_CHUNK_OVERLAP = 50;
    public const DEFAULT_TOP_K = 5;
    public const DEFAULT_SIMILARITY_THRESHOLD = 0.7;
    public const DEFAULT_RETRIEVAL_STRATEGY = 'keyword';

    /**
     * @param int $chunkSize Size of text chunks in tokens/characters
     * @param int $chunkOverlap Overlap between chunks for context continuity
     * @param int $topK Number of top results to retrieve
     * @param float $similarityThreshold Minimum similarity score (0.0-1.0)
     * @param string $retrievalStrategy Strategy: 'keyword', 'semantic', 'hybrid'
     * @param bool $rerank Whether to rerank results
     * @param bool $includeSources Include source metadata in responses
     * @param int $maxContextLength Maximum context length to pass to LLM
     * @param string $chunkingMethod Chunking method: 'sentence', 'paragraph', 'token', 'fixed'
     * @param array<string, mixed> $embeddingConfig Configuration for embedding model
     */
    public function __construct(
        private readonly int $chunkSize = self::DEFAULT_CHUNK_SIZE,
        private readonly int $chunkOverlap = self::DEFAULT_CHUNK_OVERLAP,
        private readonly int $topK = self::DEFAULT_TOP_K,
        private readonly float $similarityThreshold = self::DEFAULT_SIMILARITY_THRESHOLD,
        private readonly string $retrievalStrategy = self::DEFAULT_RETRIEVAL_STRATEGY,
        private readonly bool $rerank = false,
        private readonly bool $includeSources = true,
        private readonly int $maxContextLength = 4096,
        private readonly string $chunkingMethod = 'sentence',
        private readonly array $embeddingConfig = [],
    ) {
        $this->validateChunkSize();
        $this->validateSimilarityThreshold();
        $this->validateRetrievalStrategy();
        $this->validateChunkingMethod();
    }

    /**
     * Create from array configuration.
     *
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            chunkSize: $config['chunk_size'] ?? self::DEFAULT_CHUNK_SIZE,
            chunkOverlap: $config['chunk_overlap'] ?? self::DEFAULT_CHUNK_OVERLAP,
            topK: $config['top_k'] ?? self::DEFAULT_TOP_K,
            similarityThreshold: $config['similarity_threshold'] ?? self::DEFAULT_SIMILARITY_THRESHOLD,
            retrievalStrategy: $config['retrieval_strategy'] ?? self::DEFAULT_RETRIEVAL_STRATEGY,
            rerank: $config['rerank'] ?? false,
            includeSources: $config['include_sources'] ?? true,
            maxContextLength: $config['max_context_length'] ?? 4096,
            chunkingMethod: $config['chunking_method'] ?? 'sentence',
            embeddingConfig: $config['embedding_config'] ?? [],
        );
    }

    public function getChunkSize(): int
    {
        return $this->chunkSize;
    }

    public function getChunkOverlap(): int
    {
        return $this->chunkOverlap;
    }

    public function getTopK(): int
    {
        return $this->topK;
    }

    public function getSimilarityThreshold(): float
    {
        return $this->similarityThreshold;
    }

    public function getRetrievalStrategy(): string
    {
        return $this->retrievalStrategy;
    }

    public function shouldRerank(): bool
    {
        return $this->rerank;
    }

    public function shouldIncludeSources(): bool
    {
        return $this->includeSources;
    }

    public function getMaxContextLength(): int
    {
        return $this->maxContextLength;
    }

    public function getChunkingMethod(): string
    {
        return $this->chunkingMethod;
    }

    public function getEmbeddingConfig(): array
    {
        return $this->embeddingConfig;
    }

    /**
     * Create a new config with modified values.
     *
     * @param array<string, mixed> $overrides
     */
    public function with(array $overrides): self
    {
        return new self(
            chunkSize: $overrides['chunk_size'] ?? $this->chunkSize,
            chunkOverlap: $overrides['chunk_overlap'] ?? $this->chunkOverlap,
            topK: $overrides['top_k'] ?? $this->topK,
            similarityThreshold: $overrides['similarity_threshold'] ?? $this->similarityThreshold,
            retrievalStrategy: $overrides['retrieval_strategy'] ?? $this->retrievalStrategy,
            rerank: $overrides['rerank'] ?? $this->rerank,
            includeSources: $overrides['include_sources'] ?? $this->includeSources,
            maxContextLength: $overrides['max_context_length'] ?? $this->maxContextLength,
            chunkingMethod: $overrides['chunking_method'] ?? $this->chunkingMethod,
            embeddingConfig: $overrides['embedding_config'] ?? $this->embeddingConfig,
        );
    }

    /**
     * Check if using keyword-based retrieval.
     */
    public function isKeywordRetrieval(): bool
    {
        return $this->retrievalStrategy === 'keyword';
    }

    /**
     * Check if using semantic retrieval.
     */
    public function isSemanticRetrieval(): bool
    {
        return $this->retrievalStrategy === 'semantic';
    }

    /**
     * Check if using hybrid retrieval.
     */
    public function isHybridRetrieval(): bool
    {
        return $this->retrievalStrategy === 'hybrid';
    }

    private function validateChunkSize(): void
    {
        if ($this->chunkSize < 1) {
            throw new ConfigurationException('Chunk size must be greater than 0', 'chunk_size', $this->chunkSize);
        }
        if ($this->chunkSize > 100000) {
            throw new ConfigurationException('Chunk size is too large (max: 100000)', 'chunk_size', $this->chunkSize);
        }
    }

    private function validateSimilarityThreshold(): void
    {
        if ($this->similarityThreshold < 0.0 || $this->similarityThreshold > 1.0) {
            throw new ConfigurationException('Similarity threshold must be between 0.0 and 1.0', 'similarity_threshold', $this->similarityThreshold);
        }
    }

    private function validateRetrievalStrategy(): void
    {
        $valid = ['keyword', 'semantic', 'hybrid'];
        if (! in_array($this->retrievalStrategy, $valid, true)) {
            throw new ConfigurationException(
                "Invalid retrieval strategy: {$this->retrievalStrategy}. Must be one of: " . implode(', ', $valid),
                'retrieval_strategy',
                $this->retrievalStrategy
            );
        }
    }

    private function validateChunkingMethod(): void
    {
        $valid = ['sentence', 'paragraph', 'token', 'fixed'];
        if (! in_array($this->chunkingMethod, $valid, true)) {
            throw new ConfigurationException(
                "Invalid chunking method: {$this->chunkingMethod}. Must be one of: " . implode(', ', $valid),
                'chunking_method',
                $this->chunkingMethod
            );
        }
    }
}
