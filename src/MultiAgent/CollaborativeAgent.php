<?php

declare(strict_types=1);

namespace ClaudeAgents\MultiAgent;

use ClaudeAgents\AgentResult;
use ClaudeAgents\Contracts\CollaborativeInterface;
use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Base class for collaborative agents that can communicate with other agents.
 *
 * Provides message handling, capability declaration, and integration with
 * CollaborationManager for multi-agent systems.
 */
abstract class CollaborativeAgent implements CollaborativeInterface
{
    protected ClaudePhp $client;
    protected string $agentId;
    protected array $capabilities;
    protected array $inbox = [];
    protected array $outbox = [];
    protected ?CollaborationManager $manager = null;
    protected LoggerInterface $logger;
    protected string $model;
    protected int $maxTokens;

    /**
     * @param ClaudePhp $client Claude API client
     * @param string $agentId Unique identifier for this agent
     * @param array<string> $capabilities Agent capabilities/skills
     * @param array<string, mixed> $options Configuration options
     */
    public function __construct(
        ClaudePhp $client,
        string $agentId,
        array $capabilities = [],
        array $options = []
    ) {
        $this->client = $client;
        $this->agentId = $agentId;
        $this->capabilities = $capabilities;
        $this->logger = $options['logger'] ?? new NullLogger();
        $this->model = $options['model'] ?? 'claude-sonnet-4-5';
        $this->maxTokens = $options['max_tokens'] ?? 2048;
    }

    /**
     * Send a message to another agent.
     */
    public function sendMessage(Message $message): void
    {
        $this->outbox[] = $message;
        $this->logger->debug("Agent {$this->agentId} sent message to {$message->getTo()}");

        // If connected to a manager, route through it
        if ($this->manager) {
            $this->manager->routeMessage($message);
        }
    }

    /**
     * Receive a message from another agent.
     */
    public function receiveMessage(Message $message): void
    {
        $this->inbox[] = $message;
        $this->logger->debug("Agent {$this->agentId} received message from {$message->getFrom()}");

        // Process the message
        $this->processMessage($message);
    }

    /**
     * Get the agent's unique identifier.
     */
    public function getAgentId(): string
    {
        return $this->agentId;
    }

    /**
     * Get agent capabilities/skills.
     *
     * @return array<string>
     */
    public function getCapabilities(): array
    {
        return $this->capabilities;
    }

    /**
     * Set the collaboration manager for this agent.
     */
    public function setManager(CollaborationManager $manager): void
    {
        $this->manager = $manager;
    }

    /**
     * Get pending messages in inbox.
     *
     * @return array<Message>
     */
    public function getInbox(): array
    {
        return $this->inbox;
    }

    /**
     * Get sent messages in outbox.
     *
     * @return array<Message>
     */
    public function getOutbox(): array
    {
        return $this->outbox;
    }

    /**
     * Clear inbox messages.
     */
    public function clearInbox(): void
    {
        $this->inbox = [];
    }

    /**
     * Clear outbox messages.
     */
    public function clearOutbox(): void
    {
        $this->outbox = [];
    }

    /**
     * Get unread messages count.
     */
    public function getUnreadCount(): int
    {
        return count($this->inbox);
    }

    /**
     * Check if agent can handle a capability.
     */
    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }

    /**
     * Process a received message (override in subclasses).
     */
    protected function processMessage(Message $message): void
    {
        // Default: no-op, subclasses should override
        $this->logger->debug("Message processed by {$this->agentId}: {$message->getContent()}");
    }

    /**
     * Extract text content from Claude response.
     *
     * @param array<mixed> $content
     */
    protected function extractTextContent(array $content): string
    {
        $texts = [];

        foreach ($content as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'text') {
                $texts[] = $block['text'] ?? '';
            }
        }

        return implode("\n", $texts);
    }

    /**
     * Build context from recent messages.
     */
    protected function buildMessageContext(int $limit = 5): string
    {
        $recentMessages = array_slice($this->inbox, -$limit);

        if (empty($recentMessages)) {
            return '';
        }

        $context = "Recent messages:\n";
        foreach ($recentMessages as $msg) {
            $context .= "From {$msg->getFrom()}: {$msg->getContent()}\n";
        }

        return $context;
    }

    /**
     * Create and send a response message.
     */
    protected function reply(Message $originalMessage, string $content, string $type = 'response'): void
    {
        $reply = new Message(
            from: $this->agentId,
            to: $originalMessage->getFrom(),
            content: $content,
            type: $type,
            metadata: [
                'in_reply_to' => $originalMessage->getId(),
                'original_type' => $originalMessage->getType(),
            ]
        );

        $this->sendMessage($reply);
    }

    /**
     * Broadcast a message to all agents.
     */
    protected function broadcast(string $content, string $type = 'broadcast'): void
    {
        $message = new Message(
            from: $this->agentId,
            to: 'broadcast',
            content: $content,
            type: $type
        );

        $this->sendMessage($message);
    }

    /**
     * Abstract method: Run the agent with a task.
     */
    abstract public function run(string $task): AgentResult;

    /**
     * Abstract method: Get agent name.
     */
    abstract public function getName(): string;
}
