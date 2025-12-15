<?php

declare(strict_types=1);

namespace ClaudeAgents\Conversation\Export;

use ClaudeAgents\Contracts\SessionExporterInterface;
use ClaudeAgents\Conversation\Session;

/**
 * Export sessions to Markdown format.
 */
class MarkdownSessionExporter implements SessionExporterInterface
{
    public function export(Session $session, array $options = []): string
    {
        $includeMetadata = $options['include_metadata'] ?? false;
        $includeTimestamps = $options['include_timestamps'] ?? true;
        $style = $options['style'] ?? 'default'; // 'default', 'chat', 'detailed'

        return match ($style) {
            'chat' => $this->exportChatStyle($session, $includeTimestamps),
            'detailed' => $this->exportDetailedStyle($session, $includeMetadata, $includeTimestamps),
            default => $this->exportDefaultStyle($session, $includeMetadata, $includeTimestamps),
        };
    }

    private function exportDefaultStyle(Session $session, bool $includeMetadata, bool $includeTimestamps): string
    {
        $md = [];

        // Header
        $md[] = "# Conversation: {$session->getId()}";
        $md[] = '';
        $md[] = "**Turn Count:** {$session->getTurnCount()}";
        $md[] = '**Created:** ' . date('Y-m-d H:i:s', (int)$session->getCreatedAt());

        if ($session->getLastActivity()) {
            $md[] = '**Last Activity:** ' . date('Y-m-d H:i:s', (int)$session->getLastActivity());
        }

        if ($includeMetadata && ! empty($session->getState())) {
            $md[] = '';
            $md[] = '## Session State';
            $md[] = '';
            $md[] = '```json';
            $md[] = json_encode($session->getState(), JSON_PRETTY_PRINT);
            $md[] = '```';
        }

        $md[] = '';
        $md[] = '## Conversation';
        $md[] = '';

        // Turns
        $turnNumber = 1;
        foreach ($session->getTurns() as $turn) {
            $md[] = "### Turn {$turnNumber}";

            if ($includeTimestamps) {
                $md[] = '_' . date('Y-m-d H:i:s', (int)$turn->getTimestamp()) . '_';
            }

            $md[] = '';
            $md[] = '**User:**';
            $md[] = $turn->getUserInput();
            $md[] = '';
            $md[] = '**Agent:**';
            $md[] = $turn->getAgentResponse();

            if ($includeMetadata && ! empty($turn->getMetadata())) {
                $md[] = '';
                $md[] = '<details>';
                $md[] = '<summary>Metadata</summary>';
                $md[] = '';
                $md[] = '```json';
                $md[] = json_encode($turn->getMetadata(), JSON_PRETTY_PRINT);
                $md[] = '```';
                $md[] = '</details>';
            }

            $md[] = '';
            $md[] = '---';
            $md[] = '';

            $turnNumber++;
        }

        return implode("\n", $md);
    }

    private function exportChatStyle(Session $session, bool $includeTimestamps): string
    {
        $md = [];
        $md[] = '# Chat Conversation';
        $md[] = '';

        foreach ($session->getTurns() as $turn) {
            if ($includeTimestamps) {
                $timestamp = date('H:i:s', (int)$turn->getTimestamp());
                $md[] = "**👤 User** _{$timestamp}_";
            } else {
                $md[] = '**👤 User**';
            }
            $md[] = '> ' . str_replace("\n", "\n> ", $turn->getUserInput());
            $md[] = '';

            if ($includeTimestamps) {
                $timestamp = date('H:i:s', (int)$turn->getTimestamp());
                $md[] = "**🤖 Agent** _{$timestamp}_";
            } else {
                $md[] = '**🤖 Agent**';
            }
            $md[] = '> ' . str_replace("\n", "\n> ", $turn->getAgentResponse());
            $md[] = '';
        }

        return implode("\n", $md);
    }

    private function exportDetailedStyle(Session $session, bool $includeMetadata, bool $includeTimestamps): string
    {
        $md = [];

        $md[] = '# Detailed Conversation Report';
        $md[] = '';
        $md[] = '## Summary';
        $md[] = '';
        $md[] = '| Property | Value |';
        $md[] = '|----------|-------|';
        $md[] = "| Session ID | `{$session->getId()}` |";
        $md[] = "| Turn Count | {$session->getTurnCount()} |";
        $md[] = '| Created | ' . date('Y-m-d H:i:s', (int)$session->getCreatedAt()) . ' |';

        if ($session->getLastActivity()) {
            $duration = $session->getLastActivity() - $session->getCreatedAt();
            $md[] = '| Duration | ' . round($duration / 60, 2) . ' minutes |';
        }

        $md[] = '';
        $md[] = '## Turns';
        $md[] = '';

        foreach ($session->getTurns() as $index => $turn) {
            $turnNum = $index + 1;
            $md[] = "### Turn #{$turnNum}: {$turn->getId()}";
            $md[] = '';

            if ($includeTimestamps) {
                $md[] = '**Timestamp:** ' . date('Y-m-d H:i:s', (int)$turn->getTimestamp());
                $md[] = '';
            }

            $md[] = '#### User Input';
            $md[] = '```';
            $md[] = $turn->getUserInput();
            $md[] = '```';
            $md[] = '';

            $md[] = '#### Agent Response';
            $md[] = '```';
            $md[] = $turn->getAgentResponse();
            $md[] = '```';
            $md[] = '';

            if ($includeMetadata && ! empty($turn->getMetadata())) {
                $md[] = '#### Metadata';
                $md[] = '```json';
                $md[] = json_encode($turn->getMetadata(), JSON_PRETTY_PRINT);
                $md[] = '```';
                $md[] = '';
            }
        }

        return implode("\n", $md);
    }

    public function getFormat(): string
    {
        return 'markdown';
    }

    public function getExtension(): string
    {
        return 'md';
    }

    public function getMimeType(): string
    {
        return 'text/markdown';
    }
}
