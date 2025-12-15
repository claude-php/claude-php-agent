<?php

declare(strict_types=1);

namespace ClaudeAgents\Conversation\Storage;

use ClaudeAgents\Contracts\SessionSerializerInterface;
use ClaudeAgents\Conversation\Session;
use ClaudeAgents\Conversation\Turn;

/**
 * JSON-based session serializer.
 */
class JsonSessionSerializer implements SessionSerializerInterface
{
    public function serialize(Session $session): array
    {
        $turns = [];
        foreach ($session->getTurns() as $turn) {
            $turns[] = $turn->toArray();
        }

        return [
            'id' => $session->getId(),
            'state' => $session->getState(),
            'turns' => $turns,
            'created_at' => $session->getCreatedAt(),
            'last_activity' => $session->getLastActivity(),
            'turn_count' => $session->getTurnCount(),
            'version' => '1.0',
        ];
    }

    public function deserialize(mixed $data): ?Session
    {
        if (! is_array($data)) {
            return null;
        }

        // Validate required fields
        if (! isset($data['id'])) {
            return null;
        }

        // Create session with original ID
        $session = new Session($data['id']);

        // Restore state
        if (isset($data['state']) && is_array($data['state'])) {
            $session->setState($data['state']);
        }

        // Restore turns
        if (isset($data['turns']) && is_array($data['turns'])) {
            foreach ($data['turns'] as $turnData) {
                if (! is_array($turnData)) {
                    continue;
                }

                $turn = $this->deserializeTurn($turnData);
                if ($turn) {
                    $session->addTurn($turn);
                }
            }
        }

        return $session;
    }

    private function deserializeTurn(array $data): ?Turn
    {
        if (! isset($data['user_input']) || ! isset($data['agent_response'])) {
            return null;
        }

        $metadata = $data['metadata'] ?? [];

        return new Turn(
            $data['user_input'],
            $data['agent_response'],
            $metadata
        );
    }
}
