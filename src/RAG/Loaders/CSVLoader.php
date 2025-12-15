<?php

declare(strict_types=1);

namespace ClaudeAgents\RAG\Loaders;

/**
 * Loads documents from CSV files.
 *
 * Each row becomes a document.
 */
class CSVLoader implements DocumentLoaderInterface
{
    /**
     * @param string $filePath Path to the CSV file
     * @param string $contentColumn Column to use as document content
     * @param string|null $titleColumn Column to use as document title (optional)
     * @param array<string, mixed> $metadata Additional metadata
     */
    public function __construct(
        private readonly string $filePath,
        private readonly string $contentColumn,
        private readonly ?string $titleColumn = null,
        private readonly array $metadata = [],
    ) {
    }

    public function load(): array
    {
        if (! file_exists($this->filePath)) {
            throw new \RuntimeException("File not found: {$this->filePath}");
        }

        $handle = fopen($this->filePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Failed to open file: {$this->filePath}");
        }

        $documents = [];
        $headers = fgetcsv($handle);

        if ($headers === false) {
            fclose($handle);

            throw new \RuntimeException('Failed to read CSV headers');
        }

        $contentIndex = array_search($this->contentColumn, $headers, true);
        if ($contentIndex === false) {
            fclose($handle);

            throw new \RuntimeException("Content column '{$this->contentColumn}' not found");
        }

        $titleIndex = null;
        if ($this->titleColumn !== null) {
            $titleIndex = array_search($this->titleColumn, $headers, true);
            if ($titleIndex === false) {
                fclose($handle);

                throw new \RuntimeException("Title column '{$this->titleColumn}' not found");
            }
        }

        $rowNumber = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if (! isset($row[$contentIndex])) {
                continue;
            }

            $content = $row[$contentIndex];
            $title = $titleIndex !== null && isset($row[$titleIndex])
                ? $row[$titleIndex]
                : "Row {$rowNumber}";

            // Include all columns as metadata
            $rowMetadata = [];
            foreach ($headers as $i => $header) {
                if (isset($row[$i])) {
                    $rowMetadata[$header] = $row[$i];
                }
            }

            $documents[] = [
                'title' => $title,
                'content' => $content,
                'metadata' => array_merge(
                    [
                        'source' => $this->filePath,
                        'row_number' => $rowNumber,
                        'file_type' => 'csv',
                    ],
                    $rowMetadata,
                    $this->metadata
                ),
            ];
        }

        fclose($handle);

        return $documents;
    }
}
