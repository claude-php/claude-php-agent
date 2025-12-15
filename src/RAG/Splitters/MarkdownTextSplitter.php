<?php

declare(strict_types=1);

namespace ClaudeAgents\RAG\Splitters;

use ClaudeAgents\Contracts\ChunkerInterface;

/**
 * Splits Markdown text while preserving structure.
 *
 * Respects headers, code blocks, lists, and other Markdown elements.
 */
class MarkdownTextSplitter implements ChunkerInterface
{
    /**
     * @param int $chunkSize Target size in characters per chunk
     * @param int $overlap Number of overlapping characters between chunks
     */
    public function __construct(
        private readonly int $chunkSize = 2000,
        private readonly int $overlap = 200,
    ) {
    }

    public function chunk(string $text): array
    {
        if (empty(trim($text))) {
            return [];
        }

        // Markdown-specific separators in order of preference
        $separators = [
            "\n## ",       // H2 headers
            "\n### ",      // H3 headers
            "\n#### ",     // H4 headers
            "\n##### ",    // H5 headers
            "\n###### ",   // H6 headers
            "\n```\n",     // Code blocks
            "\n\n",        // Paragraphs
            "\n",          // Lines
            ' ',           // Words
            '',            // Characters
        ];

        $splitter = new RecursiveCharacterTextSplitter(
            chunkSize: $this->chunkSize,
            overlap: $this->overlap,
            separators: $separators
        );

        return $splitter->chunk($text);
    }

    public function getChunkSize(): int
    {
        return $this->chunkSize;
    }

    public function getOverlap(): int
    {
        return $this->overlap;
    }
}
