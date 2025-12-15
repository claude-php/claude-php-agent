<?php

declare(strict_types=1);

namespace ClaudeAgents\Observability\Exporters;

/**
 * Interface for metrics and trace exporters.
 */
interface ExporterInterface
{
    /**
     * Export data to the target backend.
     *
     * @param array<string, mixed> $data Data to export
     * @return bool Success status
     */
    public function export(array $data): bool;

    /**
     * Get the exporter name.
     */
    public function getName(): string;
}
