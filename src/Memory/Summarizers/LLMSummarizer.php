<?php

declare(strict_types=1);

namespace ClaudeAgents\Memory\Summarizers;

use ClaudePhp\ClaudePhp;

/**
 * LLM-based conversation summarizer using Claude.
 *
 * Uses Claude to generate high-quality summaries of conversation history,
 * preserving key information while reducing token usage.
 */
class LLMSummarizer implements SummarizerInterface
{
    private int $maxTokens;
    private string $focus;
    private string $model;

    /**
     * @param ClaudePhp $client Claude API client
     * @param array{max_tokens?: int, focus?: string, model?: string} $options Configuration options
     */
    public function __construct(
        private readonly ClaudePhp $client,
        array $options = []
    ) {
        $this->maxTokens = $options['max_tokens'] ?? 500;
        $this->focus = $options['focus'] ?? 'key_points';
        $this->model = $options['model'] ?? 'claude-3-5-haiku-20241022';
    }

    public function summarize(array $messages, string $existingSummary = ''): string
    {
        $prompt = $this->buildPrompt($messages, $existingSummary);

        $response = $this->client->messages()->create([
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
        ]);

        $content = $response->content[0] ?? null;
        if ($content && $content->type === 'text') {
            return trim($content->text);
        }

        return '';
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
     * Build the summarization prompt based on focus type.
     */
    private function buildPrompt(array $messages, string $existingSummary): string
    {
        $conversationText = $this->formatMessages($messages);

        $focusInstructions = match ($this->focus) {
            'key_points' => 'Focus on extracting the main points, decisions, and important information.',
            'entities' => 'Focus on identifying and tracking people, places, organizations, and important entities mentioned.',
            'decisions' => 'Focus on decisions made, action items, and commitments.',
            'chronological' => 'Provide a chronological summary of events and topics discussed.',
            default => 'Provide a concise summary of the conversation.',
        };

        if ($existingSummary) {
            return <<<PROMPT
                You are summarizing a conversation. You have an existing summary and new messages to incorporate.

                EXISTING SUMMARY:
                {$existingSummary}

                NEW MESSAGES:
                {$conversationText}

                Create an updated summary that combines the existing summary with the new information. {$focusInstructions}

                Keep the summary concise but comprehensive. Use clear, factual language.

                UPDATED SUMMARY:
                PROMPT;
        }

        return <<<PROMPT
            You are summarizing a conversation. {$focusInstructions}

            CONVERSATION:
            {$conversationText}

            Provide a concise summary that captures the essential information. Use clear, factual language.

            SUMMARY:
            PROMPT;
    }

    /**
     * Format messages into readable text.
     *
     * @param array<array<string, mixed>> $messages
     */
    private function formatMessages(array $messages): string
    {
        $formatted = [];

        foreach ($messages as $message) {
            $role = ucfirst($message['role'] ?? 'unknown');
            $content = $this->extractContent($message);

            if ($content) {
                $formatted[] = "{$role}: {$content}";
            }
        }

        return implode("\n\n", $formatted);
    }

    /**
     * Extract text content from a message.
     *
     * @param array<string, mixed> $message
     */
    private function extractContent(array $message): string
    {
        $content = $message['content'] ?? '';

        if (is_string($content)) {
            return $content;
        }

        if (is_array($content)) {
            $texts = [];
            foreach ($content as $block) {
                if (isset($block['type']) && $block['type'] === 'text' && isset($block['text'])) {
                    $texts[] = $block['text'];
                }
            }

            return implode(' ', $texts);
        }

        return '';
    }
}
