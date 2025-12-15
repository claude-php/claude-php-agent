<?php

declare(strict_types=1);

namespace ClaudeAgents\RAG\Loaders;

/**
 * Loads documents from text files.
 */
class TextFileLoader implements DocumentLoaderInterface
{
    /**
     * @param string $filePath Path to the text file
     * @param array<string, mixed> $metadata Additional metadata
     */
    public function __construct(
        private readonly string $filePath,
        private readonly array $metadata = [],
    ) {
    }

    public function load(): array
    {
        if (! file_exists($this->filePath)) {
            throw new \RuntimeException("File not found: {$this->filePath}");
        }

        if (! is_readable($this->filePath)) {
            throw new \RuntimeException("File not readable: {$this->filePath}");
        }

        $content = file_get_contents($this->filePath);
        if ($content === false) {
            throw new \RuntimeException("Failed to read file: {$this->filePath}");
        }

        $title = basename($this->filePath);

        return [[
            'title' => $title,
            'content' => $content,
            'metadata' => array_merge(
                [
                    'source' => $this->filePath,
                    'file_type' => 'text',
                    'file_size' => filesize($this->filePath),
                ],
                $this->metadata
            ),
        ]];
    }
}
