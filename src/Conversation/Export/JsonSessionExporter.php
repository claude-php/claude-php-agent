<?php

declare(strict_types=1);

namespace ClaudeAgents\Conversation\Export;

use ClaudeAgents\Contracts\SessionExporterInterface;
use ClaudeAgents\Conversation\Session;

/**
 * Export sessions to JSON format.
 */
class JsonSessionExporter implements SessionExporterInterface
{
    public function export(Session $session, array $options = []): string
    {
        $prettyPrint = $options['pretty_print'] ?? true;
        $includeMeta = $options['include_metadata'] ?? true;

        $data = [
            'session_id' => $session->getId(),
            'turn_count' => $session->getTurnCount(),
            'created_at' => date('Y-m-d H:i:s', (int)$session->getCreatedAt()),
            'last_activity' => $session->getLastActivity()
                ? date('Y-m-d H:i:s', (int)$session->getLastActivity())
                : null,
        ];

        if ($includeMeta) {
            $data['state'] = $session->getState();
        }

        $data['turns'] = [];
        foreach ($session->getTurns() as $turn) {
            $turnData = [
                'id' => $turn->getId(),
                'timestamp' => date('Y-m-d H:i:s', (int)$turn->getTimestamp()),
                'user_input' => $turn->getUserInput(),
                'agent_response' => $turn->getAgentResponse(),
            ];

            if ($includeMeta) {
                $turnData['metadata'] = $turn->getMetadata();
            }

            $data['turns'][] = $turnData;
        }

        $flags = $prettyPrint ? JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE : JSON_UNESCAPED_UNICODE;

        return json_encode($data, $flags);
    }

    public function getFormat(): string
    {
        return 'json';
    }

    public function getExtension(): string
    {
        return 'json';
    }

    public function getMimeType(): string
    {
        return 'application/json';
    }
}
