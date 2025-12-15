<?php

declare(strict_types=1);

namespace ClaudeAgents\Conversation\Search;

use ClaudeAgents\Contracts\TurnSearchInterface;
use ClaudeAgents\Conversation\Session;

/**
 * Default implementation for searching conversation turns.
 */
class TurnSearch implements TurnSearchInterface
{
    public function searchByContent(Session $session, string $query, array $options = []): array
    {
        $caseSensitive = $options['case_sensitive'] ?? false;
        $wholeWord = $options['whole_word'] ?? false;
        $searchIn = $options['search_in'] ?? 'both'; // 'user', 'agent', or 'both'

        $results = [];
        $pattern = $this->buildSearchPattern($query, $caseSensitive, $wholeWord);

        foreach ($session->getTurns() as $turn) {
            $matches = false;

            if (in_array($searchIn, ['user', 'both'])) {
                if (preg_match($pattern, $turn->getUserInput())) {
                    $matches = true;
                }
            }

            if (! $matches && in_array($searchIn, ['agent', 'both'])) {
                if (preg_match($pattern, $turn->getAgentResponse())) {
                    $matches = true;
                }
            }

            if ($matches) {
                $results[] = $turn;
            }
        }

        return $results;
    }

    public function searchByMetadata(Session $session, array $criteria): array
    {
        $results = [];

        foreach ($session->getTurns() as $turn) {
            $metadata = $turn->getMetadata();

            if ($this->matchesCriteria($metadata, $criteria)) {
                $results[] = $turn;
            }
        }

        return $results;
    }

    public function searchByTimeRange(Session $session, float $startTime, float $endTime): array
    {
        $results = [];

        foreach ($session->getTurns() as $turn) {
            $timestamp = $turn->getTimestamp();

            if ($timestamp >= $startTime && $timestamp <= $endTime) {
                $results[] = $turn;
            }
        }

        return $results;
    }

    public function searchByPattern(Session $session, string $pattern, string $field = 'both'): array
    {
        $results = [];

        foreach ($session->getTurns() as $turn) {
            $matches = false;

            try {
                if (in_array($field, ['user_input', 'both'])) {
                    if (preg_match($pattern, $turn->getUserInput())) {
                        $matches = true;
                    }
                }

                if (! $matches && in_array($field, ['agent_response', 'both'])) {
                    if (preg_match($pattern, $turn->getAgentResponse())) {
                        $matches = true;
                    }
                }
            } catch (\Exception $e) {
                // Invalid regex pattern, skip
                continue;
            }

            if ($matches) {
                $results[] = $turn;
            }
        }

        return $results;
    }

    /**
     * Build regex pattern for content search.
     */
    private function buildSearchPattern(string $query, bool $caseSensitive, bool $wholeWord): string
    {
        $escaped = preg_quote($query, '/');

        if ($wholeWord) {
            $pattern = '/\b' . $escaped . '\b/';
        } else {
            $pattern = '/' . $escaped . '/';
        }

        if (! $caseSensitive) {
            $pattern .= 'i';
        }

        return $pattern;
    }

    /**
     * Check if metadata matches all criteria.
     */
    private function matchesCriteria(array $metadata, array $criteria): bool
    {
        foreach ($criteria as $key => $value) {
            if (! isset($metadata[$key])) {
                return false;
            }

            // Support nested criteria
            if (is_array($value) && is_array($metadata[$key])) {
                if (! $this->matchesCriteria($metadata[$key], $value)) {
                    return false;
                }
            } elseif ($metadata[$key] !== $value) {
                return false;
            }
        }

        return true;
    }
}
