<?php

declare(strict_types=1);

namespace ClaudeAgents\RAG\Loaders;

/**
 * Loads all documents from a directory.
 */
class DirectoryLoader implements DocumentLoaderInterface
{
    /**
     * @param string $directoryPath Path to the directory
     * @param array<string> $extensions File extensions to include (e.g., ['txt', 'md'])
     * @param bool $recursive Whether to scan subdirectories
     * @param array<string, mixed> $metadata Additional metadata
     */
    public function __construct(
        private readonly string $directoryPath,
        private readonly array $extensions = ['txt', 'md'],
        private readonly bool $recursive = false,
        private readonly array $metadata = [],
    ) {
    }

    public function load(): array
    {
        if (! is_dir($this->directoryPath)) {
            throw new \RuntimeException("Directory not found: {$this->directoryPath}");
        }

        $documents = [];
        $files = $this->getFiles($this->directoryPath);

        foreach ($files as $file) {
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            if (! in_array($extension, $this->extensions, true)) {
                continue;
            }

            $loader = new TextFileLoader($file, $this->metadata);
            $docs = $loader->load();
            $documents = array_merge($documents, $docs);
        }

        return $documents;
    }

    /**
     * Get all files in directory.
     *
     * @return array<string>
     */
    private function getFiles(string $directory): array
    {
        $files = [];
        $iterator = $this->recursive
            ? new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
            )
            : new \DirectoryIterator($directory);

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
