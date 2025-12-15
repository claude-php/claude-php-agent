<?php

declare(strict_types=1);

namespace ClaudeAgents\Contracts;

use ClaudeAgents\Conversation\Session;

/**
 * Interface for serializing and deserializing sessions.
 */
interface SessionSerializerInterface
{
    /**
     * Serialize a session to a string or array.
     *
     * @param Session $session The session to serialize
     * @return mixed Serialized data
     */
    public function serialize(Session $session): mixed;

    /**
     * Deserialize data back to a Session object.
     *
     * @param mixed $data The serialized data
     * @return Session|null The deserialized session or null on failure
     */
    public function deserialize(mixed $data): ?Session;
}
