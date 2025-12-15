<?php

declare(strict_types=1);

namespace ClaudeAgents\Conversation\Export;

use ClaudeAgents\Contracts\SessionExporterInterface;
use ClaudeAgents\Conversation\Session;

/**
 * Export sessions to CSV format.
 */
class CsvSessionExporter implements SessionExporterInterface
{
    public function export(Session $session, array $options = []): string
    {
        $includeMetadata = $options['include_metadata'] ?? false;
        $delimiter = $options['delimiter'] ?? ',';
        $enclosure = $options['enclosure'] ?? '"';

        $output = fopen('php://temp', 'r+');

        // Write header
        $headers = ['Turn ID', 'Timestamp', 'User Input', 'Agent Response'];
        if ($includeMetadata) {
            $headers[] = 'Metadata';
        }
        fputcsv($output, $headers, $delimiter, $enclosure);

        // Write turns
        foreach ($session->getTurns() as $turn) {
            $row = [
                $turn->getId(),
                date('Y-m-d H:i:s', (int)$turn->getTimestamp()),
                $turn->getUserInput(),
                $turn->getAgentResponse(),
            ];

            if ($includeMetadata) {
                $row[] = json_encode($turn->getMetadata());
            }

            fputcsv($output, $row, $delimiter, $enclosure);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    public function getFormat(): string
    {
        return 'csv';
    }

    public function getExtension(): string
    {
        return 'csv';
    }

    public function getMimeType(): string
    {
        return 'text/csv';
    }
}
