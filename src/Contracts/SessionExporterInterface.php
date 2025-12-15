<?php

declare(strict_types=1);

namespace ClaudeAgents\Contracts;

use ClaudeAgents\Conversation\Session;

/**
 * Interface for exporting sessions to various formats.
 */
interface SessionExporterInterface
{
    /**
     * Export a session to the specified format.
     *
     * @param Session $session The session to export
     * @param array $options Export options
     * @return string The exported content
     */
    public function export(Session $session, array $options = []): string;

    /**
     * Get the format name for this exporter.
     *
     * @return string Format name (e.g., 'json', 'csv', 'markdown')
     */
    public function getFormat(): string;

    /**
     * Get the file extension for this format.
     *
     * @return string File extension (e.g., 'json', 'csv', 'md')
     */
    public function getExtension(): string;

    /**
     * Get the MIME type for this format.
     *
     * @return string MIME type
     */
    public function getMimeType(): string;
}
