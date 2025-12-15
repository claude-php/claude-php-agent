<?php

declare(strict_types=1);

namespace ClaudeAgents\Agents;

use ClaudeAgents\AgentResult;
use ClaudeAgents\Contracts\AgentInterface;
use ClaudePhp\ClaudePhp;

/**
 * Worker Agent for hierarchical systems.
 *
 * A specialized agent that handles specific domains or tasks
 * as part of a larger hierarchical agent system.
 */
class WorkerAgent implements AgentInterface
{
    private ClaudePhp $client;
    private string $name;
    private string $specialty;
    private string $systemPrompt;
    private string $model;
    private int $maxTokens;

    /**
     * @param ClaudePhp $client The Claude API client
     * @param array<string, mixed> $options Configuration:
     *   - name: Worker name/identifier
     *   - specialty: Description of what this worker specializes in
     *   - system: System prompt for the worker
     *   - model: Model to use
     *   - max_tokens: Max tokens per response
     */
    public function __construct(ClaudePhp $client, array $options = [])
    {
        $this->client = $client;
        $this->name = $options['name'] ?? 'worker';
        $this->specialty = $options['specialty'] ?? 'general tasks';
        $this->systemPrompt = $options['system'] ?? "You are a specialized worker agent for {$this->specialty}.";
        $this->model = $options['model'] ?? 'claude-sonnet-4-5';
        $this->maxTokens = $options['max_tokens'] ?? 2048;
    }

    public function run(string $task): AgentResult
    {
        try {
            $response = $this->client->messages()->create([
                'model' => $this->model,
                'max_tokens' => $this->maxTokens,
                'system' => $this->systemPrompt,
                'messages' => [
                    ['role' => 'user', 'content' => $task],
                ],
            ]);

            $answer = $this->extractTextContent($response->content ?? []);

            return AgentResult::success(
                answer: $answer,
                messages: [['role' => 'user', 'content' => $task]],
                iterations: 1,
                metadata: [
                    'worker' => $this->name,
                    'specialty' => $this->specialty,
                    'token_usage' => [
                        'input' => $response->usage->input_tokens ?? 0,
                        'output' => $response->usage->output_tokens ?? 0,
                        'total' => ($response->usage->input_tokens ?? 0) + ($response->usage->output_tokens ?? 0),
                    ],
                ],
            );
        } catch (\Throwable $e) {
            return AgentResult::failure(
                error: "Worker '{$this->name}' error: {$e->getMessage()}",
                metadata: ['worker' => $this->name],
            );
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the worker's specialty.
     */
    public function getSpecialty(): string
    {
        return $this->specialty;
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
