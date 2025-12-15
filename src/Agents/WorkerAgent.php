<?php

declare(strict_types=1);

namespace ClaudeAgents\Agents;

use ClaudeAgents\AgentResult;
use ClaudePhp\ClaudePhp;

/**
 * Worker Agent for hierarchical systems.
 *
 * A specialized agent that handles specific domains or tasks
 * as part of a larger hierarchical agent system.
 */
class WorkerAgent extends AbstractAgent
{
    private string $specialty;
    private string $systemPrompt;

    protected const DEFAULT_NAME = 'worker';

    /**
     * @param ClaudePhp $client The Claude API client
     * @param array<string, mixed> $options Configuration:
     *   - name: Worker name/identifier
     *   - specialty: Description of what this worker specializes in
     *   - system: System prompt for the worker
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
        $this->specialty = $options['specialty'] ?? 'general tasks';
        $this->systemPrompt = $options['system'] ?? "You are a specialized worker agent for {$this->specialty}.";
    }

    public function run(string $task): AgentResult
    {
        $this->logStart($task, ['specialty' => $this->specialty]);

        try {
            $response = $this->client->messages()->create([
                'model' => $this->model,
                'max_tokens' => $this->maxTokens,
                'system' => $this->systemPrompt,
                'messages' => [
                    ['role' => 'user', 'content' => $task],
                ],
            ]);

            $answer = $this->extractTextContent($response);

            $this->logSuccess(['specialty' => $this->specialty]);

            return AgentResult::success(
                answer: $answer,
                messages: [['role' => 'user', 'content' => $task]],
                iterations: 1,
                metadata: [
                    'worker' => $this->name,
                    'specialty' => $this->specialty,
                    'token_usage' => $this->formatTokenUsage($response),
                ],
            );
        } catch (\Throwable $e) {
            $this->logError($e->getMessage());

            return AgentResult::failure(
                error: "Worker '{$this->name}' error: {$e->getMessage()}",
                metadata: ['worker' => $this->name],
            );
        }
    }

    /**
     * Get the worker's specialty.
     */
    public function getSpecialty(): string
    {
        return $this->specialty;
    }
}
