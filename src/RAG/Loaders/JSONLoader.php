<?php

declare(strict_types=1);

namespace ClaudeAgents\RAG\Loaders;

/**
 * Loads documents from JSON files.
 *
 * Expects an array of objects or a single object.
 */
class JSONLoader implements DocumentLoaderInterface
{
    /**
     * @param string $filePath Path to the JSON file
     * @param string $contentField Field to use as document content
     * @param string|null $titleField Field to use as document title (optional)
     * @param string|null $jsonPointer JSON Pointer to array of documents (e.g., '/data/items')
     * @param array<string, mixed> $metadata Additional metadata
     */
    public function __construct(
        private readonly string $filePath,
        private readonly string $contentField = 'content',
        private readonly ?string $titleField = 'title',
        private readonly ?string $jsonPointer = null,
        private readonly array $metadata = [],
    ) {
    }

    public function load(): array
    {
        if (! file_exists($this->filePath)) {
            throw new \RuntimeException("File not found: {$this->filePath}");
        }

        $content = file_get_contents($this->filePath);
        if ($content === false) {
            throw new \RuntimeException("Failed to read file: {$this->filePath}");
        }

        $data = json_decode($content, true);
        if ($data === null) {
            throw new \RuntimeException('Failed to parse JSON: ' . json_last_error_msg());
        }

        // Navigate to JSON pointer if specified
        if ($this->jsonPointer !== null) {
            $data = $this->resolveJsonPointer($data, $this->jsonPointer);
        }

        // Ensure data is an array
        if (! is_array($data)) {
            throw new \RuntimeException('Expected array of documents');
        }

        // Handle single object vs array of objects
        $items = $this->isAssociativeArray($data) ? [$data] : $data;

        $documents = [];
        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            if (! isset($item[$this->contentField])) {
                continue;
            }

            $content = $item[$this->contentField];
            $title = $this->titleField !== null && isset($item[$this->titleField])
                ? $item[$this->titleField]
                : 'Document ' . ($index + 1);

            $documents[] = [
                'title' => $title,
                'content' => $content,
                'metadata' => array_merge(
                    [
                        'source' => $this->filePath,
                        'index' => $index,
                        'file_type' => 'json',
                    ],
                    $item,
                    $this->metadata
                ),
            ];
        }

        return $documents;
    }

    /**
     * Resolve JSON Pointer (RFC 6901).
     *
     * @param mixed $data
     */
    private function resolveJsonPointer(mixed $data, string $pointer): mixed
    {
        if ($pointer === '' || $pointer === '/') {
            return $data;
        }

        $parts = explode('/', ltrim($pointer, '/'));

        foreach ($parts as $part) {
            $part = str_replace('~1', '/', str_replace('~0', '~', $part));

            if (is_array($data) && array_key_exists($part, $data)) {
                $data = $data[$part];
            } else {
                throw new \RuntimeException("JSON Pointer path not found: {$pointer}");
            }
        }

        return $data;
    }

    /**
     * Check if array is associative (single object).
     *
     * @param array<mixed> $array
     */
    private function isAssociativeArray(array $array): bool
    {
        if (empty($array)) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }
}
