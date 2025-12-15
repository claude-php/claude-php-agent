<?php

declare(strict_types=1);

namespace ClaudeAgents\RAG\Loaders;

/**
 * Interface for loading documents from various sources.
 */
interface DocumentLoaderInterface
{
    /**
     * Load documents from the source.
     *
     * @return array<array{title: string, content: string, metadata: array<string, mixed>}>
     */
    public function load(): array;
}
