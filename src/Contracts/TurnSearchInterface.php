<?php

declare(strict_types=1);

namespace ClaudeAgents\Contracts;

use ClaudeAgents\Conversation\Session;
use ClaudeAgents\Conversation\Turn;

/**
 * Interface for searching conversation turns.
 */
interface TurnSearchInterface
{
    /**
     * Search turns by content (user input or agent response).
     *
     * @param Session $session The session to search
     * @param string $query The search query
     * @param array $options Search options (case_sensitive, whole_word, etc.)
     * @return array<Turn> Array of matching turns
     */
    public function searchByContent(Session $session, string $query, array $options = []): array;

    /**
     * Search turns by metadata.
     *
     * @param Session $session The session to search
     * @param array $criteria Metadata criteria to match
     * @return array<Turn> Array of matching turns
     */
    public function searchByMetadata(Session $session, array $criteria): array;

    /**
     * Search turns by timestamp range.
     *
     * @param Session $session The session to search
     * @param float $startTime Start timestamp (inclusive)
     * @param float $endTime End timestamp (inclusive)
     * @return array<Turn> Array of matching turns
     */
    public function searchByTimeRange(Session $session, float $startTime, float $endTime): array;

    /**
     * Find turns containing specific patterns (regex).
     *
     * @param Session $session The session to search
     * @param string $pattern Regex pattern to match
     * @param string $field Field to search ('user_input', 'agent_response', or 'both')
     * @return array<Turn> Array of matching turns
     */
    public function searchByPattern(Session $session, string $pattern, string $field = 'both'): array;
}
