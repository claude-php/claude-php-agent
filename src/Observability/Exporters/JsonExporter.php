<?php

declare(strict_types=1);

namespace ClaudeAgents\Observability\Exporters;

/**
 * Export metrics and traces to JSON files.
 */
class JsonExporter implements ExporterInterface
{
    public function __construct(
        private readonly string $outputPath,
        private readonly bool $prettyPrint = true,
    ) {
    }

    public function export(array $data): bool
    {
        $flags = JSON_THROW_ON_ERROR;
        if ($this->prettyPrint) {
            $flags |= JSON_PRETTY_PRINT;
        }

        try {
            $json = json_encode($data, $flags);

            // Ensure directory exists
            $dir = dirname($this->outputPath);
            if (! is_dir($dir)) {
                mkdir($dir, 0o755, true);
            }

            file_put_contents($this->outputPath, $json);

            return true;
        } catch (\JsonException $e) {
            return false;
        }
    }

    public function getName(): string
    {
        return 'json';
    }

    public function getOutputPath(): string
    {
        return $this->outputPath;
    }
}
