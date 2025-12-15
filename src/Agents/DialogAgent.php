<?php

declare(strict_types=1);

namespace ClaudeAgents\Agents;

use ClaudeAgents\AgentResult;
use ClaudeAgents\Contracts\AgentInterface;
use ClaudeAgents\Contracts\ConversationalInterface;
use ClaudeAgents\Conversation\Session;
use ClaudeAgents\Conversation\Turn;
use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Dialog Agent - Manages multi-turn conversations with context.
 */
class DialogAgent implements AgentInterface, ConversationalInterface
{
    private ClaudePhp $client;
    private string $name;
    private array $sessions = [];
    private ?string $currentSessionId = null;
    private LoggerInterface $logger;

    public function __construct(ClaudePhp $client, array $options = [])
    {
        $this->client = $client;
        $this->name = $options['name'] ?? 'dialog_agent';
        $this->logger = $options['logger'] ?? new NullLogger();
    }

    public function run(string $task): AgentResult
    {
        // Single-turn execution
        $this->startConversation();
        $response = $this->turn($task);

        return AgentResult::success(
            answer: $response,
            messages: [],
            iterations: 1,
            metadata: ['session_id' => $this->currentSessionId],
        );
    }

    public function startConversation(?string $sessionId = null): Session
    {
        $session = new Session($sessionId);
        $this->sessions[$session->getId()] = $session;
        $this->currentSessionId = $session->getId();

        $this->logger->info("Started conversation: {$session->getId()}");

        return $session;
    }

    public function turn(string $userInput, ?string $sessionId = null): string
    {
        $sessionId ??= $this->currentSessionId;

        if (! $sessionId || ! isset($this->sessions[$sessionId])) {
            $session = $this->startConversation($sessionId);
            $sessionId = $session->getId();
        } else {
            $session = $this->sessions[$sessionId];
        }

        // Build context from previous turns
        $context = $this->buildContext($session);

        // Generate response
        $response = $this->generateResponse($userInput, $context, $session->getState());

        // Record turn
        $turn = new Turn($userInput, $response);
        $session->addTurn($turn);

        $this->logger->info('Dialog turn completed', ['session' => $sessionId]);

        return $response;
    }

    public function getSession(string $sessionId): ?Session
    {
        return $this->sessions[$sessionId] ?? null;
    }

    public function getName(): string
    {
        return $this->name;
    }

    private function buildContext(Session $session): string
    {
        $turns = $session->getTurns();
        $recentTurns = array_slice($turns, -5); // Last 5 turns

        $context = '';
        foreach ($recentTurns as $turn) {
            $context .= "User: {$turn->getUserInput()}\n";
            $context .= "Agent: {$turn->getAgentResponse()}\n\n";
        }

        return $context;
    }

    private function generateResponse(string $userInput, string $context, array $state): string
    {
        $stateStr = json_encode($state);

        $prompt = empty($context) ? $userInput : <<<PROMPT
            Previous conversation:
            {$context}

            Current state: {$stateStr}

            User: {$userInput}
            PROMPT;

        $response = $this->client->messages()->create([
            'model' => 'claude-sonnet-4-5',
            'max_tokens' => 1024,
            'system' => 'You are a conversational agent. Maintain context across turns.',
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        return $this->extractTextContent($response->content ?? []);
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
