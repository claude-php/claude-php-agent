<?php

declare(strict_types=1);

namespace ClaudeAgents\RAG;

/**
 * Represents a chunk of text from a document.
 */
class Chunk
{
    /**
     * @param string $id Unique chunk identifier
     * @param string $documentId ID of the source document
     * @param string $text The chunk text
     * @param int $startIndex Character position in original document
     * @param int $endIndex Character position in original document
     * @param array<string, mixed> $metadata Additional metadata (source, category, etc.)
     */
    public function __construct(
        private readonly string $id,
        private readonly string $documentId,
        private readonly string $text,
        private readonly int $startIndex,
        private readonly int $endIndex,
        private readonly array $metadata = [],
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getDocumentId(): string
    {
        return $this->documentId;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getStartIndex(): int
    {
        return $this->startIndex;
    }

    public function getEndIndex(): int
    {
        return $this->endIndex;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get a metadata value.
     */
    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Get chunk length in characters.
     */
    public function getLength(): int
    {
        return strlen($this->text);
    }

    /**
     * Get approximate word count.
     */
    public function getWordCount(): int
    {
        return str_word_count($this->text);
    }

    /**
     * Get source document title if available.
     */
    public function getSource(): ?string
    {
        return $this->metadata['source'] ?? null;
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'document_id' => $this->documentId,
            'text' => $this->text,
            'start_index' => $this->startIndex,
            'end_index' => $this->endIndex,
            'metadata' => $this->metadata,
            'length' => $this->getLength(),
            'word_count' => $this->getWordCount(),
        ];
    }
}
