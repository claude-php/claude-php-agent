<?php

declare(strict_types=1);

namespace ClaudeAgents\Agents;

use ClaudeAgents\AgentResult;
use ClaudeAgents\Reasoning\CoTPrompts;
use ClaudePhp\ClaudePhp;

/**
 * Chain-of-Thought agent for step-by-step reasoning.
 */
class ChainOfThoughtAgent extends AbstractAgent
{
    private string $mode; // 'zero_shot' or 'few_shot'
    private string $trigger;

    /**
     * @var array<int|string, mixed>
     */
    private array $examples;

    protected const DEFAULT_NAME = 'cot_agent';

    /**
     * @param ClaudePhp $client Claude API client
     * @param array<string, mixed> $options Configuration:
     *   - name: Agent name
     *   - mode: 'zero_shot' (default) or 'few_shot'
     *   - trigger: CoT trigger phrase
     *   - examples: Few-shot examples (for few_shot mode)
     *   - model: Model to use
     *   - max_tokens: Max tokens per response
     *   - logger: PSR-3 logger
     */
    public function __construct(ClaudePhp $client, array $options = [])
    {
        parent::__construct($client, $options);
    }

    /**
     * Initialize agent-specific configuration.
     *
     * @param array<string, mixed> $options
     */
    protected function initialize(array $options): void
    {
        $this->mode = $options['mode'] ?? 'zero_shot';
        $this->trigger = $options['trigger'] ?? CoTPrompts::zeroShotTrigger();
        $this->examples = $options['examples'] ?? CoTPrompts::mathExamples();
    }

    public function run(string $task): AgentResult
    {
        $this->logStart($task);

        try {
            $systemPrompt = $this->buildSystemPrompt();
            $userPrompt = $task;

            // Add trigger for zero-shot
            if ($this->mode === 'zero_shot') {
                $userPrompt .= "\n\n" . $this->trigger;
            }

            $response = $this->client->messages()->create([
                'model' => $this->model,
                'max_tokens' => $this->maxTokens,
                'system' => $systemPrompt,
                'messages' => [['role' => 'user', 'content' => $userPrompt]],
            ]);

            $answer = $this->extractTextContent($response);

            $this->logSuccess(['reasoning_mode' => $this->mode]);

            return AgentResult::success(
                answer: $answer,
                messages: [],
                iterations: 1,
                metadata: [
                    'reasoning_mode' => $this->mode,
                    'tokens' => $this->formatSimpleTokenUsage($response),
                ],
            );
        } catch (\Throwable $e) {
            $this->logError($e->getMessage());

            return AgentResult::failure(error: $e->getMessage());
        }
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
}
