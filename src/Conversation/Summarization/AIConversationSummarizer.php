<?php

declare(strict_types=1);

namespace ClaudeAgents\Conversation\Summarization;

use ClaudeAgents\Contracts\ConversationSummarizerInterface;
use ClaudeAgents\Conversation\Session;
use ClaudePhp\ClaudePhp;

/**
 * AI-powered conversation summarizer using Claude API.
 */
class AIConversationSummarizer implements ConversationSummarizerInterface
{
    private ClaudePhp $client;
    private string $model;

    public function __construct(ClaudePhp $client, string $model = 'claude-sonnet-4-5')
    {
        $this->client = $client;
        $this->model = $model;
    }

    public function summarize(Session $session, array $options = []): string
    {
        $maxTokens = $options['max_tokens'] ?? 300;
        $style = $options['style'] ?? 'concise'; // 'concise', 'detailed', 'bullet_points'

        $conversation = $this->buildConversationText($session);

        $styleInstructions = match ($style) {
            'detailed' => 'Provide a detailed summary covering all major points.',
            'bullet_points' => 'Summarize as a bullet-point list of key points.',
            default => 'Provide a concise summary of the main topics and outcomes.',
        };

        $prompt = <<<PROMPT
            Summarize the following conversation. {$styleInstructions}

            Conversation:
            {$conversation}

            Summary:
            PROMPT;

        $response = $this->client->messages()->create([
            'model' => $this->model,
            'max_tokens' => $maxTokens,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        return $this->extractTextContent($response->content ?? []);
    }

    public function extractTopics(Session $session, int $maxTopics = 5): array
    {
        $conversation = $this->buildConversationText($session);

        $prompt = <<<PROMPT
            Extract the {$maxTopics} main topics discussed in this conversation. Return only the topics as a comma-separated list.

            Conversation:
            {$conversation}

            Topics:
            PROMPT;

        $response = $this->client->messages()->create([
            'model' => $this->model,
            'max_tokens' => 150,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        $topicsText = $this->extractTextContent($response->content ?? []);
        $topics = array_map('trim', explode(',', $topicsText));

        return array_slice($topics, 0, $maxTopics);
    }

    public function summarizeTurns(Session $session): array
    {
        $summaries = [];

        foreach ($session->getTurns() as $turn) {
            $prompt = <<<PROMPT
                Summarize this conversation turn in one sentence:

                User: {$turn->getUserInput()}
                Agent: {$turn->getAgentResponse()}

                Summary:
                PROMPT;

            $response = $this->client->messages()->create([
                'model' => $this->model,
                'max_tokens' => 100,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

            $summaries[] = [
                'turn_id' => $turn->getId(),
                'summary' => trim($this->extractTextContent($response->content ?? [])),
                'timestamp' => $turn->getTimestamp(),
            ];
        }

        return $summaries;
    }

    private function buildConversationText(Session $session): string
    {
        $lines = [];

        foreach ($session->getTurns() as $turn) {
            $lines[] = 'User: ' . $turn->getUserInput();
            $lines[] = 'Agent: ' . $turn->getAgentResponse();
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    private function extractTextContent(array $content): string
    {
        $texts = [];
        foreach ($content as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'text') {
                $texts[] = $block['text'] ?? '';
            }
        }

        return implode("\n", $texts);
    }
}
