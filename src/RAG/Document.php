<?php

declare(strict_types=1);

namespace ClaudeAgents\RAG;

/**
 * Represents a document in the knowledge base.
 */
class Document
{
    /**
     * @param string $id Unique document identifier
     * @param string $title Document title
     * @param string $content Full document content
     * @param array<string, mixed> $metadata Additional metadata
     */
    public function __construct(
        private readonly string $id,
        private readonly string $title,
        private readonly string $content,
        private readonly array $metadata = [],
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getContent(): string
    {
        return $this->content;
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
     * Get content length in characters.
     */
    public function getLength(): int
    {
        return strlen($this->content);
    }

    /**
     * Get approximate word count.
     */
    public function getWordCount(): int
    {
        return str_word_count($this->content);
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
            'title' => $this->title,
            'content' => $this->content,
            'metadata' => $this->metadata,
            'length' => $this->getLength(),
            'word_count' => $this->getWordCount(),
        ];
    }
}
