<?php

declare(strict_types=1);

namespace ClaudeAgents\Conversation\Summarization;

use ClaudeAgents\Contracts\ConversationSummarizerInterface;
use ClaudeAgents\Conversation\Session;

/**
 * Basic conversation summarizer without AI (rule-based).
 */
class BasicConversationSummarizer implements ConversationSummarizerInterface
{
    public function summarize(Session $session, array $options = []): string
    {
        $maxLength = $options['max_length'] ?? 500;
        $includeTurnCount = $options['include_turn_count'] ?? true;
        $includeTopics = $options['include_topics'] ?? true;

        $turns = $session->getTurns();
        $turnCount = count($turns);

        if ($turnCount === 0) {
            return 'Empty conversation with no turns.';
        }

        $summary = [];

        if ($includeTurnCount) {
            $summary[] = "Conversation with {$turnCount} turn(s).";
        }

        // Add duration if available
        if ($session->getLastActivity()) {
            $duration = $session->getLastActivity() - $session->getCreatedAt();
            $minutes = round($duration / 60);
            if ($minutes > 0) {
                $summary[] = "Duration: {$minutes} minute(s).";
            }
        }

        // Add topics if requested
        if ($includeTopics) {
            $topics = $this->extractTopics($session, 3);
            if (! empty($topics)) {
                $summary[] = 'Topics: ' . implode(', ', $topics) . '.';
            }
        }

        // Add excerpt from first and last turn
        if ($turnCount > 0) {
            $firstTurn = $turns[0];
            $summary[] = 'Started with: "' . $this->truncate($firstTurn->getUserInput(), 100) . '"';

            if ($turnCount > 1) {
                $lastTurn = $turns[$turnCount - 1];
                $summary[] = 'Ended with: "' . $this->truncate($lastTurn->getUserInput(), 100) . '"';
            }
        }

        $summaryText = implode(' ', $summary);

        return $this->truncate($summaryText, $maxLength);
    }

    public function extractTopics(Session $session, int $maxTopics = 5): array
    {
        $allText = '';

        foreach ($session->getTurns() as $turn) {
            $allText .= ' ' . $turn->getUserInput();
            $allText .= ' ' . $turn->getAgentResponse();
        }

        // Extract significant words (simple approach)
        $words = str_word_count(strtolower($allText), 1);
        $stopWords = $this->getStopWords();

        // Filter stop words and short words
        $significantWords = array_filter($words, function ($word) use ($stopWords) {
            return strlen($word) > 3 && ! in_array($word, $stopWords);
        });

        // Count word frequency
        $wordCounts = array_count_values($significantWords);
        arsort($wordCounts);

        // Get top words as topics
        return array_slice(array_keys($wordCounts), 0, $maxTopics);
    }

    public function summarizeTurns(Session $session): array
    {
        $summaries = [];

        foreach ($session->getTurns() as $turn) {
            $userInput = $this->truncate($turn->getUserInput(), 100);
            $agentResponse = $this->truncate($turn->getAgentResponse(), 100);

            $summaries[] = [
                'turn_id' => $turn->getId(),
                'summary' => "User: {$userInput} | Agent: {$agentResponse}",
                'timestamp' => $turn->getTimestamp(),
            ];
        }

        return $summaries;
    }

    /**
     * Truncate text to specified length.
     */
    private function truncate(string $text, int $maxLength): string
    {
        if (strlen($text) <= $maxLength) {
            return $text;
        }

        return substr($text, 0, $maxLength - 3) . '...';
    }

    /**
     * Get list of common stop words.
     */
    private function getStopWords(): array
    {
        return [
            'the', 'be', 'to', 'of', 'and', 'a', 'in', 'that', 'have', 'i',
            'it', 'for', 'not', 'on', 'with', 'he', 'as', 'you', 'do', 'at',
            'this', 'but', 'his', 'by', 'from', 'they', 'we', 'say', 'her', 'she',
            'or', 'an', 'will', 'my', 'one', 'all', 'would', 'there', 'their',
            'what', 'so', 'up', 'out', 'if', 'about', 'who', 'get', 'which', 'go',
            'me', 'when', 'make', 'can', 'like', 'time', 'no', 'just', 'him', 'know',
            'take', 'people', 'into', 'year', 'your', 'good', 'some', 'could', 'them',
            'see', 'other', 'than', 'then', 'now', 'look', 'only', 'come', 'its', 'over',
            'think', 'also', 'back', 'after', 'use', 'two', 'how', 'our', 'work',
        ];
    }
}
