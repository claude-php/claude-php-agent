<?php

declare(strict_types=1);

namespace ClaudeAgents\MultiAgent;

use ClaudeAgents\AgentResult;
use ClaudeAgents\Contracts\AgentInterface;
use ClaudeAgents\Contracts\CollaborativeInterface;
use ClaudeAgents\Observability\Metrics;
use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Collaboration Manager - Orchestrates multiple agents working together.
 *
 * AutoGen-style multi-agent collaboration with message routing,
 * conversation orchestration, and shared context management.
 */
class CollaborationManager
{
    private ClaudePhp $client;
    private array $agents = [];
    private array $messageQueue = [];
    private array $conversationHistory = [];
    private ?Protocol $protocol = null;
    private ?SharedMemory $sharedMemory = null;
    private LoggerInterface $logger;
    private Metrics $metrics;
    private int $maxRounds;
    private bool $enableMessagePassing;
    private int $messagesRouted = 0;

    /**
     * @param ClaudePhp $client Claude API client
     * @param array<string, mixed> $options Configuration:
     *   - max_rounds: Maximum collaboration rounds (default: 10)
     *   - protocol: Communication protocol
     *   - shared_memory: SharedMemory instance for agent coordination
     *   - enable_message_passing: Enable true message passing between agents (default: true)
     *   - logger: PSR-3 logger
     */
    public function __construct(ClaudePhp $client, array $options = [])
    {
        $this->client = $client;
        $this->maxRounds = $options['max_rounds'] ?? 10;
        $this->protocol = $options['protocol'] ?? Protocol::requestResponse();
        $this->sharedMemory = $options['shared_memory'] ?? new SharedMemory();
        $this->enableMessagePassing = $options['enable_message_passing'] ?? true;
        $this->logger = $options['logger'] ?? new NullLogger();
        $this->metrics = new Metrics();
    }

    /**
     * Register an agent for collaboration.
     *
     * @param string $id Agent identifier
     * @param AgentInterface $agent Agent instance
     * @param array<string> $capabilities Agent capabilities
     */
    public function registerAgent(string $id, AgentInterface $agent, array $capabilities = []): void
    {
        $this->agents[$id] = [
            'agent' => $agent,
            'capabilities' => $capabilities,
            'message_count' => 0,
        ];

        // Set manager reference if it's a CollaborativeAgent
        if ($agent instanceof CollaborativeAgent) {
            $agent->setManager($this);
        }

        $this->logger->info("Registered agent: {$id}", ['capabilities' => $capabilities]);
    }

    /**
     * Unregister an agent.
     */
    public function unregisterAgent(string $id): bool
    {
        if (isset($this->agents[$id])) {
            unset($this->agents[$id]);
            $this->logger->info("Unregistered agent: {$id}");

            return true;
        }

        return false;
    }

    /**
     * Start collaboration on a task.
     */
    public function collaborate(string $task): AgentResult
    {
        $this->logger->info('Starting collaboration', ['task' => substr($task, 0, 100)]);

        if (empty($this->agents)) {
            return AgentResult::failure(error: 'No agents registered');
        }

        try {
            // Initialize collaboration
            $this->conversationHistory = [];
            $this->messageQueue = [];

            // Determine which agent should start
            $initiator = $this->selectInitiator($task);

            // Run collaboration rounds
            $round = 0;
            $currentAgent = $initiator;
            $currentTask = $task;

            while ($round < $this->maxRounds) {
                $round++;
                $this->logger->info("Collaboration round {$round}");

                // Agent processes task
                $result = $this->executeAgent($currentAgent, $currentTask);

                // Record in conversation
                $this->conversationHistory[] = [
                    'round' => $round,
                    'agent' => $currentAgent,
                    'task' => $currentTask,
                    'result' => $result->getAnswer(),
                    'timestamp' => microtime(true),
                ];

                // Check if collaboration is complete
                if ($this->isCollaborationComplete($result)) {
                    $this->logger->info("Collaboration complete at round {$round}");

                    break;
                }

                // Determine next agent and task
                $next = $this->selectNextAgent($currentAgent, $result);
                if (! $next) {
                    break; // No more agents to involve
                }

                $currentAgent = $next['agent'];
                $currentTask = $next['task'];
            }

            // Synthesize final result
            $finalResult = $this->synthesizeResults();

            return AgentResult::success(
                answer: $finalResult,
                messages: [],
                iterations: $round,
                metadata: [
                    'rounds' => $round,
                    'agents_involved' => array_keys(array_unique(array_column($this->conversationHistory, 'agent'))),
                    'conversation_length' => count($this->conversationHistory),
                ],
            );
        } catch (\Throwable $e) {
            $this->logger->error("Collaboration failed: {$e->getMessage()}");

            return AgentResult::failure(error: $e->getMessage());
        }
    }

    /**
     * Send a message between agents.
     */
    public function sendMessage(Message $message): void
    {
        // Validate against protocol
        if ($this->protocol && ! $this->protocol->validateMessage($message)) {
            $this->logger->warning('Message rejected by protocol', [
                'protocol' => $this->protocol->getName(),
                'message_type' => $message->getType(),
            ]);

            return;
        }

        $this->messageQueue[] = $message;
        $this->logger->debug("Message queued: {$message->getFrom()} → {$message->getTo()}");

        // If message passing is enabled, deliver immediately
        if ($this->enableMessagePassing) {
            $this->deliverMessage($message);
        }
    }

    /**
     * Route a message from an agent (used by CollaborativeAgent).
     */
    public function routeMessage(Message $message): void
    {
        $this->sendMessage($message);
    }

    /**
     * Deliver a message to its recipient(s).
     */
    private function deliverMessage(Message $message): void
    {
        $this->messagesRouted++;
        $this->metrics->recordRequest(true, 0, 0, 0); // Track message routing

        if ($message->isBroadcast()) {
            // Deliver to all agents except sender
            foreach ($this->agents as $id => $info) {
                if ($id !== $message->getFrom() && $info['agent'] instanceof CollaborativeInterface) {
                    $info['agent']->receiveMessage($message);
                    $this->logger->debug("Broadcast message delivered to: {$id}");
                }
            }
        } else {
            // Deliver to specific agent
            $recipientId = $message->getTo();
            if (isset($this->agents[$recipientId])) {
                $agent = $this->agents[$recipientId]['agent'];
                if ($agent instanceof CollaborativeInterface) {
                    $agent->receiveMessage($message);
                    $this->logger->debug("Message delivered to: {$recipientId}");
                } else {
                    $this->logger->warning("Agent {$recipientId} cannot receive messages (not CollaborativeInterface)");
                }
            } else {
                $this->logger->warning("Recipient agent not found: {$recipientId}");
            }
        }
    }

    /**
     * Process all pending messages in queue.
     */
    public function processMessageQueue(): int
    {
        $processed = 0;
        while (! empty($this->messageQueue)) {
            $message = array_shift($this->messageQueue);
            $this->deliverMessage($message);
            $processed++;
        }

        return $processed;
    }

    /**
     * Get conversation history.
     *
     * @return array<array>
     */
    public function getConversationHistory(): array
    {
        return $this->conversationHistory;
    }

    /**
     * Get shared memory instance.
     */
    public function getSharedMemory(): SharedMemory
    {
        return $this->sharedMemory;
    }

    /**
     * Get collaboration metrics.
     *
     * @return array<string, mixed>
     */
    public function getMetrics(): array
    {
        return [
            'agents_registered' => count($this->agents),
            'messages_routed' => $this->messagesRouted,
            'messages_in_queue' => count($this->messageQueue),
            'conversation_length' => count($this->conversationHistory),
            'shared_memory_stats' => $this->sharedMemory->getStatistics(),
            'performance' => $this->metrics->getSummary(),
        ];
    }

    /**
     * Select initial agent based on task.
     */
    private function selectInitiator(string $task): string
    {
        // Use LLM to match task to agent capabilities
        $agentInfo = [];
        foreach ($this->agents as $id => $info) {
            $capabilities = implode(', ', $info['capabilities']);
            $agentInfo[] = "{$id}: {$capabilities}";
        }

        $agentsStr = implode("\n", $agentInfo);
        $prompt = <<<PROMPT
            Task: {$task}

            Available agents:
            {$agentsStr}

            Which agent should handle this task first? Respond with just the agent ID.
            PROMPT;

        try {
            $response = $this->client->messages()->create([
                'model' => 'claude-sonnet-4-5',
                'max_tokens' => 128,
                'system' => 'Select the most appropriate agent for the task.',
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

            $selectedId = trim($this->extractTextContent($response->content ?? []));

            return isset($this->agents[$selectedId]) ? $selectedId : array_key_first($this->agents);
        } catch (\Throwable $e) {
            $this->logger->warning("Failed to select initiator: {$e->getMessage()}");

            return array_key_first($this->agents);
        }
    }

    /**
     * Execute an agent on a task.
     */
    private function executeAgent(string $agentId, string $task): AgentResult
    {
        $agent = $this->agents[$agentId]['agent'];
        $this->agents[$agentId]['message_count']++;

        $this->logger->info("Executing agent: {$agentId}");

        return $agent->run($task);
    }

    /**
     * Check if collaboration is complete.
     */
    private function isCollaborationComplete(AgentResult $result): bool
    {
        // Check if result indicates completion
        $answer = strtolower($result->getAnswer());

        $completionIndicators = [
            'task complete',
            'finished',
            'done',
            'no further action needed',
            'final result',
        ];

        foreach ($completionIndicators as $indicator) {
            if (str_contains($answer, $indicator)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Select next agent to involve in collaboration.
     *
     * @return array{agent: string, task: string}|null
     */
    private function selectNextAgent(string $currentAgent, AgentResult $result): ?array
    {
        // Use LLM to determine if another agent should be involved
        $answer = $result->getAnswer();

        $agentList = array_keys($this->agents);
        $otherAgents = array_filter($agentList, fn ($id) => $id !== $currentAgent);

        if (empty($otherAgents)) {
            return null;
        }

        $agentsStr = implode(', ', $otherAgents);
        $prompt = <<<PROMPT
            Current agent result: {$answer}

            Available agents: {$agentsStr}

            Should another agent be involved? If yes, which agent and what should their task be?
            Respond in format: "AGENT_ID: task description" or "COMPLETE" if no more agents needed.
            PROMPT;

        try {
            $response = $this->client->messages()->create([
                'model' => 'claude-sonnet-4-5',
                'max_tokens' => 256,
                'system' => 'Determine if another agent should be involved in the collaboration.',
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

            $decision = trim($this->extractTextContent($response->content ?? []));

            if (stripos($decision, 'COMPLETE') !== false) {
                return null;
            }

            // Parse "AGENT_ID: task"
            if (preg_match('/^(\w+):\s*(.+)$/i', $decision, $matches)) {
                $nextAgent = trim($matches[1]);
                $nextTask = trim($matches[2]);

                if (isset($this->agents[$nextAgent])) {
                    return ['agent' => $nextAgent, 'task' => $nextTask];
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning("Failed to select next agent: {$e->getMessage()}");
        }

        return null;
    }

    /**
     * Synthesize final result from conversation history.
     */
    private function synthesizeResults(): string
    {
        if (empty($this->conversationHistory)) {
            return 'No collaboration occurred';
        }

        // Format conversation
        $conversation = '';
        foreach ($this->conversationHistory as $entry) {
            $conversation .= "[{$entry['agent']}] {$entry['result']}\n\n";
        }

        $prompt = <<<PROMPT
            Collaboration conversation:
            {$conversation}

            Synthesize a final, comprehensive result from this multi-agent collaboration.
            PROMPT;

        try {
            $response = $this->client->messages()->create([
                'model' => 'claude-sonnet-4-5',
                'max_tokens' => 1024,
                'system' => 'Synthesize results from multi-agent collaboration.',
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

            return $this->extractTextContent($response->content ?? []);
        } catch (\Throwable $e) {
            $this->logger->error("Synthesis failed: {$e->getMessage()}");

            // Fallback: return last agent's result
            $last = end($this->conversationHistory);

            return $last['result'] ?? 'Collaboration completed';
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
