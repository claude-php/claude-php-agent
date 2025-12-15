<?php

declare(strict_types=1);

namespace ClaudeAgents\Debate;

use ClaudeAgents\Contracts\DebateAgentInterface;
use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Agent with a specific perspective that participates in debates.
 */
class DebateAgent implements DebateAgentInterface
{
    private LoggerInterface $logger;

    /**
     * @param ClaudePhp $client Claude API client
     * @param string $name Agent name/role
     * @param string $perspective The agent's stance or perspective
     * @param string $systemPrompt System prompt defining the agent's behavior
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private readonly ClaudePhp $client,
        private readonly string $name,
        private readonly string $perspective,
        private readonly string $systemPrompt,
        array $options = [],
    ) {
        $this->logger = $options['logger'] ?? new NullLogger();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPerspective(): string
    {
        return $this->perspective;
    }

    public function getSystemPrompt(): string
    {
        return $this->systemPrompt;
    }

    public function speak(string $topic, string $context = '', string $instruction = ''): string
    {
        $this->logger->debug("Agent {$this->name} speaking on topic");

        $prompt = "Topic: {$topic}\n\n";

        if (! empty($context)) {
            $prompt .= "Previous discussion:\n{$context}\n\n";
        }

        if (! empty($instruction)) {
            $prompt .= "{$instruction}\n\n";
        }

        $prompt .= 'Provide your perspective.';

        try {
            $response = $this->client->messages()->create([
                'model' => 'claude-sonnet-4-5',
                'max_tokens' => 1024,
                'system' => $this->systemPrompt,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

            return $this->extractTextContent($response->content ?? []);
        } catch (\Throwable $e) {
            $this->logger->error("Agent {$this->name} failed: {$e->getMessage()}");

            return "Error from {$this->name}: {$e->getMessage()}";
        }
    }

    /**
     * Extract text content from response blocks.
     *
     * @param array<mixed> $content
     */
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
