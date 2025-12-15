<?php

declare(strict_types=1);

namespace ClaudeAgents\RAG\Splitters;

use ClaudeAgents\Contracts\ChunkerInterface;

/**
 * Splits code while respecting language structure.
 *
 * Tries to keep functions, classes, and logical blocks together.
 */
class CodeTextSplitter implements ChunkerInterface
{
    /**
     * @param string $language Programming language (php, python, javascript, etc.)
     * @param int $chunkSize Target size in characters per chunk
     * @param int $overlap Number of overlapping characters between chunks
     */
    public function __construct(
        private readonly string $language = 'php',
        private readonly int $chunkSize = 2000,
        private readonly int $overlap = 200,
    ) {
    }

    public function chunk(string $text): array
    {
        if (empty(trim($text))) {
            return [];
        }

        $separators = $this->getSeparatorsForLanguage($this->language);
        $splitter = new RecursiveCharacterTextSplitter(
            chunkSize: $this->chunkSize,
            overlap: $this->overlap,
            separators: $separators
        );

        return $splitter->chunk($text);
    }

    /**
     * Get appropriate separators for the programming language.
     *
     * @return array<string>
     */
    private function getSeparatorsForLanguage(string $language): array
    {
        return match (strtolower($language)) {
            'php' => [
                "\nclass ",
                "\ninterface ",
                "\ntrait ",
                "\nfunction ",
                "\npublic function ",
                "\nprotected function ",
                "\nprivate function ",
                "\n\n",
                "\n",
                ' ',
                '',
            ],
            'python' => [
                "\nclass ",
                "\ndef ",
                "\n\n",
                "\n",
                ' ',
                '',
            ],
            'javascript', 'typescript' => [
                "\nclass ",
                "\nfunction ",
                "\nconst ",
                "\nlet ",
                "\nvar ",
                "\nexport ",
                "\nimport ",
                "\n\n",
                "\n",
                ' ',
                '',
            ],
            'java', 'csharp' => [
                "\nclass ",
                "\ninterface ",
                "\nenum ",
                "\npublic ",
                "\nprotected ",
                "\nprivate ",
                "\n\n",
                "\n",
                ' ',
                '',
            ],
            'go' => [
                "\nfunc ",
                "\ntype ",
                "\nconst ",
                "\nvar ",
                "\n\n",
                "\n",
                ' ',
                '',
            ],
            'rust' => [
                "\nfn ",
                "\nstruct ",
                "\nenum ",
                "\nimpl ",
                "\ntrait ",
                "\n\n",
                "\n",
                ' ',
                '',
            ],
            default => ["\n\n", "\n", ' ', ''],
        };
    }

    public function getChunkSize(): int
    {
        return $this->chunkSize;
    }

    public function getOverlap(): int
    {
        return $this->overlap;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }
}
