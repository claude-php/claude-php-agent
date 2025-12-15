<?php

declare(strict_types=1);

namespace ClaudeAgents\MultiAgent;

use ClaudeAgents\AgentResult;

/**
 * Simple collaborative agent implementation for multi-agent systems.
 *
 * A general-purpose agent that can participate in collaborations,
 * send/receive messages, and execute tasks using Claude.
 */
class SimpleCollaborativeAgent extends CollaborativeAgent
{
    private string $name;
    private string $systemPrompt;
    private bool $autoReply;

    /**
     * @param \ClaudePhp\ClaudePhp $client
     * @param string $agentId
     * @param array<string> $capabilities
     * @param array<string, mixed> $options Configuration:
     *   - name: Display name for the agent
     *   - system_prompt: System prompt to define behavior
     *   - auto_reply: Automatically reply to messages (default: true)
     *   - model: Claude model to use
     *   - max_tokens: Max tokens per response
     *   - logger: PSR-3 logger
     */
    public function __construct(
        \ClaudePhp\ClaudePhp $client,
        string $agentId,
        array $capabilities = [],
        array $options = []
    ) {
        parent::__construct($client, $agentId, $capabilities, $options);

        $this->name = $options['name'] ?? $agentId;
        $this->systemPrompt = $options['system_prompt'] ?? $this->buildDefaultSystemPrompt();
        $this->autoReply = $options['auto_reply'] ?? true;
    }

    /**
     * Run the agent with a task.
     */
    public function run(string $task): AgentResult
    {
        $this->logger->info("Agent {$this->agentId} processing task", [
            'task' => substr($task, 0, 100),
        ]);

        try {
            // Build context from recent messages
            $messageContext = $this->buildMessageContext();

            // Construct the full prompt
            $fullPrompt = $messageContext ? "{$messageContext}\n\nCurrent task: {$task}" : $task;

            // Call Claude API
            $response = $this->client->messages()->create([
                'model' => $this->model,
                'max_tokens' => $this->maxTokens,
                'system' => $this->systemPrompt,
                'messages' => [
                    ['role' => 'user', 'content' => $fullPrompt],
                ],
            ]);

            $answer = $this->extractTextContent($response->content ?? []);

            return AgentResult::success(
                answer: $answer,
                messages: [],
                iterations: 1,
                metadata: [
                    'agent_id' => $this->agentId,
                    'capabilities' => $this->capabilities,
                    'inbox_count' => count($this->inbox),
                    'outbox_count' => count($this->outbox),
                ]
            );
        } catch (\Throwable $e) {
            $this->logger->error("Agent {$this->agentId} task failed: {$e->getMessage()}");

            return AgentResult::failure(error: $e->getMessage());
        }
    }

    /**
     * Get agent name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Process received messages.
     */
    protected function processMessage(Message $message): void
    {
        parent::processMessage($message);

        // Auto-reply to requests if enabled
        if ($this->autoReply && $message->getType() === 'request') {
            $this->handleRequest($message);
        }
    }

    /**
     * Handle an incoming request message.
     */
    private function handleRequest(Message $message): void
    {
        try {
            $result = $this->run($message->getContent());
            $this->reply($message, $result->getAnswer(), 'response');
        } catch (\Throwable $e) {
            $this->logger->error("Failed to handle request: {$e->getMessage()}");
            $this->reply($message, "Error: {$e->getMessage()}", 'error');
        }
    }

    /**
     * Build default system prompt based on capabilities.
     */
    private function buildDefaultSystemPrompt(): string
    {
        $capStr = empty($this->capabilities)
            ? 'a general-purpose assistant'
            : 'specialized in: ' . implode(', ', $this->capabilities);

        return "You are {$this->name}, {$capStr}. " .
               'You are working in a multi-agent system and may receive messages from other agents. ' .
               'Provide clear, helpful responses and collaborate effectively.';
    }
}
