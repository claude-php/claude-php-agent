<?php

declare(strict_types=1);

namespace ClaudeAgents\Agents;

use ClaudeAgents\AgentResult;
use ClaudeAgents\Contracts\AgentInterface;
use ClaudeAgents\Reasoning\CoTPrompts;
use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Chain-of-Thought agent for step-by-step reasoning.
 */
class ChainOfThoughtAgent implements AgentInterface
{
    private string $name;
    private string $mode; // 'zero_shot' or 'few_shot'
    private string $trigger;
    private array $examples;
    private LoggerInterface $logger;

    /**
     * @param ClaudePhp $client Claude API client
     * @param array<string, mixed> $options Configuration:
     *   - name: Agent name
     *   - mode: 'zero_shot' (default) or 'few_shot'
     *   - trigger: CoT trigger phrase
     *   - examples: Few-shot examples (for few_shot mode)
     *   - logger: PSR-3 logger
     */
    public function __construct(
        private readonly ClaudePhp $client,
        array $options = [],
    ) {
        $this->name = $options['name'] ?? 'cot_agent';
        $this->mode = $options['mode'] ?? 'zero_shot';
        $this->trigger = $options['trigger'] ?? CoTPrompts::zeroShotTrigger();
        $this->examples = $options['examples'] ?? CoTPrompts::mathExamples();
        $this->logger = $options['logger'] ?? new NullLogger();
    }

    public function run(string $task): AgentResult
    {
        $this->logger->info("CoT Agent: {$task}");

        try {
            $systemPrompt = $this->buildSystemPrompt();
            $userPrompt = $task;

            // Add trigger for zero-shot
            if ($this->mode === 'zero_shot') {
                $userPrompt .= "\n\n" . $this->trigger;
            }

            $response = $this->client->messages()->create([
                'model' => 'claude-sonnet-4-5',
                'max_tokens' => 2048,
                'system' => $systemPrompt,
                'messages' => [['role' => 'user', 'content' => $userPrompt]],
            ]);

            $answer = $this->extractTextContent($response->content ?? []);

            return AgentResult::success(
                answer: $answer,
                messages: [],
                iterations: 1,
                metadata: [
                    'reasoning_mode' => $this->mode,
                    'tokens' => [
                        'input' => $response->usage->input_tokens ?? 0,
                        'output' => $response->usage->output_tokens ?? 0,
                    ],
                ],
            );
        } catch (\Throwable $e) {
            $this->logger->error("CoT Agent failed: {$e->getMessage()}");

            return AgentResult::failure(error: $e->getMessage());
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Build the system prompt based on mode.
     */
    private function buildSystemPrompt(): string
    {
        if ($this->mode === 'few_shot') {
            return CoTPrompts::fewShotSystem($this->examples);
        }

        return 'You are an expert problem solver. ' .
               'Think through problems carefully, showing your reasoning step by step. ' .
               'Make your reasoning transparent and easy to follow.';
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
