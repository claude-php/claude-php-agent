<?php

declare(strict_types=1);

namespace ClaudeAgents\Memory\Summarizers;

/**
 * Extractive summarizer using keyword-based extraction.
 *
 * Provides fast, deterministic summarization without LLM calls.
 * Useful as a fallback or for low-latency requirements.
 */
class ExtractiveSummarizer implements SummarizerInterface
{
    private int $maxSentences;
    private int $maxTokens;

    /**
     * @param array{max_sentences?: int, max_tokens?: int} $options
     */
    public function __construct(array $options = [])
    {
        $this->maxSentences = $options['max_sentences'] ?? 5;
        $this->maxTokens = $options['max_tokens'] ?? 200;
    }

    public function summarize(array $messages, string $existingSummary = ''): string
    {
        $text = $this->extractText($messages);

        if (empty($text)) {
            return $existingSummary;
        }

        $sentences = $this->splitIntoSentences($text);
        $scored = $this->scoreSentences($sentences, $text);

        // Sort by score descending
        arsort($scored);

        // Take top N sentences
        $topSentences = array_slice(array_keys($scored), 0, $this->maxSentences, true);

        // Sort by original order
        sort($topSentences);

        $summary = [];
        foreach ($topSentences as $idx) {
            $summary[] = $sentences[$idx];
        }

        $summaryText = implode(' ', $summary);

        // Combine with existing summary if provided
        if ($existingSummary) {
            return $existingSummary . ' ' . $summaryText;
        }

        return $summaryText;
    }

    public function summarizeMessages(array $messages): string
    {
        return $this->summarize($messages);
    }

    public function getMaxLength(): int
    {
        return $this->maxTokens;
    }

    /**
     * Extract text from messages.
     *
     * @param array<array<string, mixed>> $messages
     */
    private function extractText(array $messages): string
    {
        $texts = [];

        foreach ($messages as $message) {
            $content = $message['content'] ?? '';

            if (is_string($content)) {
                $texts[] = $content;
            } elseif (is_array($content)) {
                foreach ($content as $block) {
                    if (isset($block['type']) && $block['type'] === 'text' && isset($block['text'])) {
                        $texts[] = $block['text'];
                    }
                }
            }
        }

        return implode(' ', $texts);
    }

    /**
     * Split text into sentences.
     *
     * @return array<int, string>
     */
    private function splitIntoSentences(string $text): array
    {
        // Simple sentence splitting
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        if ($sentences === false) {
            return [];
        }

        return array_values(array_filter($sentences, fn ($s) => strlen(trim($s)) > 10));
    }

    /**
     * Score sentences based on keyword frequency and position.
     *
     * @param array<int, string> $sentences
     * @return array<int, float>
     */
    private function scoreSentences(array $sentences, string $fullText): array
    {
        $keywords = $this->extractKeywords($fullText);
        $scores = [];

        foreach ($sentences as $idx => $sentence) {
            $score = 0.0;

            // Position bonus (earlier sentences are often more important)
            $positionBonus = 1.0 / (($idx / max(count($sentences), 1)) + 1);
            $score += $positionBonus * 0.3;

            // Keyword frequency
            $lowerSentence = strtolower($sentence);
            foreach ($keywords as $keyword => $frequency) {
                if (stripos($lowerSentence, $keyword) !== false) {
                    $score += $frequency;
                }
            }

            // Length bonus (prefer medium-length sentences)
            $wordCount = str_word_count($sentence);
            if ($wordCount >= 5 && $wordCount <= 30) {
                $score += 0.2;
            }

            $scores[$idx] = $score;
        }

        return $scores;
    }

    /**
     * Extract keywords with frequencies.
     *
     * @return array<string, float>
     */
    private function extractKeywords(string $text): array
    {
        $words = str_word_count(strtolower($text), 1);

        // Stop words to exclude
        $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
                      'of', 'with', 'by', 'from', 'up', 'about', 'into', 'through', 'during',
                      'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had',
                      'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might',
                      'can', 'this', 'that', 'these', 'those', 'i', 'you', 'he', 'she', 'it',
                      'we', 'they', 'what', 'which', 'who', 'when', 'where', 'why', 'how'];

        $filtered = array_filter($words, fn ($w) => ! in_array($w, $stopWords) && strlen($w) > 2);

        $frequencies = array_count_values($filtered);
        $total = count($filtered);

        if ($total === 0) {
            return [];
        }

        // Normalize frequencies
        $normalized = [];
        foreach ($frequencies as $word => $count) {
            $normalized[$word] = $count / $total;
        }

        // Sort by frequency and take top keywords
        arsort($normalized);

        return array_slice($normalized, 0, 20, true);
    }
}
