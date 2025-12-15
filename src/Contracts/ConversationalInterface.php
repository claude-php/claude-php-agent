<?php

declare(strict_types=1);

namespace ClaudeAgents\Contracts;

use ClaudeAgents\Conversation\Session;
use ClaudeAgents\Conversation\Turn;

/**
 * Interface for conversational agents that manage dialog state.
 */
interface ConversationalInterface extends AgentInterface
{
    /**
     * Start a new conversation.
     */
    public function startConversation(?string $sessionId = null): Session;

    /**
     * Process a turn in the conversation.
     */
    public function turn(string $userInput, ?string $sessionId = null): string;

    /**
     * Get conversation session.
     */
    public function getSession(string $sessionId): ?Session;
}
