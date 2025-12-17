<?php

declare(strict_types=1);

namespace ClaudeAgents\Agents;

use ClaudeAgents\AgentResult;
use ClaudeAgents\Contracts\AgentInterface;
use ClaudeAgents\Contracts\ConversationalInterface;
use ClaudeAgents\Conversation\Session;
use ClaudeAgents\Conversation\Turn;
use ClaudeAgents\ML\Traits\LearnableAgent;
use ClaudeAgents\ML\Traits\ParameterOptimizer;
use ClaudeAgents\ML\Traits\StrategySelector;
use ClaudeAgents\Support\TextContentExtractor;
use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Dialog Agent - Manages multi-turn conversations with context.
 *
 * **ML-Enhanced Features:**
 * - Learns optimal context window size (number of past turns)
 * - Learns when to ask clarifying questions
 * - Learns when to summarize conversation history
 * - Adapts conversation strategies based on patterns
 *
 * @package ClaudeAgents\Agents
 */
class DialogAgent implements AgentInterface, ConversationalInterface
{
    use LearnableAgent;
    use ParameterOptimizer;
    use StrategySelector;

    private ClaudePhp $client;
    private string $name;
    private array $sessions = [];
    private ?string $currentSessionId = null;
    private LoggerInterface $logger;
    private bool $useMLOptimization = false;
    private int $defaultContextWindow = 5;

    public function __construct(ClaudePhp $client, array $options = [])
    {
        $this->client = $client;
        $this->name = $options['name'] ?? 'dialog_agent';
        $this->logger = $options['logger'] ?? new NullLogger();
        $this->defaultContextWindow = $options['context_window'] ?? 5;
        $this->useMLOptimization = $options['enable_ml_optimization'] ?? false;

        // Enable ML features if requested
        if ($this->useMLOptimization) {
            $historyPath = $options['ml_history_path'] ?? 'storage/dialog_history.json';

            $this->enableLearning($historyPath);

            $this->enableParameterOptimization(
                historyPath: str_replace('.json', '_params.json', $historyPath),
                defaults: [
                    'context_window' => $this->defaultContextWindow,
                    'max_context_tokens' => 1000,
                ]
            );

            $this->enableStrategyLearning(
                strategies: ['direct_response', 'clarifying_question', 'summarize_context'],
                defaultStrategy: 'direct_response',
                historyPath: str_replace('.json', '_strategy.json', $historyPath)
            );
        }
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
        $startTime = microtime(true);
        $sessionId ??= $this->currentSessionId;

        if (! $sessionId || ! isset($this->sessions[$sessionId])) {
            $session = $this->startConversation($sessionId);
            $sessionId = $session->getId();
        } else {
            $session = $this->sessions[$sessionId];
        }

        // Learn optimal parameters if ML enabled
        $contextWindow = $this->defaultContextWindow;
        $strategy = 'direct_response';

        if ($this->useMLOptimization) {
            $params = $this->learnOptimalParameters($userInput, ['context_window']);
            $contextWindow = (int)($params['context_window'] ?? $this->defaultContextWindow);

            $strategyInfo = $this->getStrategyConfidence($userInput);
            $strategy = $strategyInfo['strategy'];

            $this->logger->debug("ML-optimized dialog", [
                'context_window' => $contextWindow,
                'strategy' => $strategy,
                'confidence' => $strategyInfo['confidence'],
            ]);
        }

        // Build context with learned window size
        $context = $this->buildContext($session, $contextWindow);

        // Generate response based on learned strategy
        $response = $this->generateResponse($userInput, $context, $session->getState(), $strategy);

        // Record turn
        $turn = new Turn($userInput, $response);
        $session->addTurn($turn);

        // Record for ML learning
        if ($this->useMLOptimization) {
            $duration = microtime(true) - $startTime;
            $this->recordTurnPerformance($userInput, $response, $contextWindow, $strategy, $duration);
        }

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

    private function buildContext(Session $session, int $contextWindow = 5): string
    {
        $turns = $session->getTurns();
        $recentTurns = array_slice($turns, -$contextWindow);

        $context = '';
        foreach ($recentTurns as $turn) {
            $context .= "User: {$turn->getUserInput()}\n";
            $context .= "Agent: {$turn->getAgentResponse()}\n\n";
        }

        return $context;
    }

    private function generateResponse(string $userInput, string $context, array $state, string $strategy = 'direct_response'): string
    {
        $stateStr = json_encode($state);

        // Adjust system prompt based on strategy
        $systemPrompt = match ($strategy) {
            'clarifying_question' => 'You are a conversational agent. When unclear, ask clarifying questions before responding.',
            'summarize_context' => 'You are a conversational agent. Periodically summarize the conversation to maintain clarity.',
            default => 'You are a conversational agent. Maintain context across turns and respond directly.',
        };

        $prompt = empty($context) ? $userInput : <<<PROMPT
            Previous conversation:
            {$context}

            Current state: {$stateStr}

            User: {$userInput}
            PROMPT;

        $response = $this->client->messages()->create([
            'model' => 'claude-sonnet-4-5',
            'max_tokens' => 1024,
            'system' => $systemPrompt,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        return TextContentExtractor::extractFromResponse($response);
    }

    /**
     * Record turn performance for learning.
     */
    private function recordTurnPerformance(
        string $userInput,
        string $response,
        int $contextWindow,
        string $strategy,
        float $duration
    ): void {
        // Create a simple result for learning
        $result = AgentResult::success(
            answer: $response,
            messages: [],
            iterations: 1
        );

        $this->recordExecution($userInput, $result, [
            'duration' => $duration,
            'response_length' => strlen($response),
        ]);

        $qualityScore = $this->evaluateDialogQuality($userInput, $response);

        $this->recordParameterPerformance(
            $userInput,
            parameters: ['context_window' => $contextWindow],
            success: true,
            qualityScore: $qualityScore,
            duration: $duration
        );

        $this->recordStrategyPerformance(
            $userInput,
            strategy: $strategy,
            success: true,
            qualityScore: $qualityScore,
            duration: $duration
        );
    }

    /**
     * Evaluate dialog quality.
     */
    private function evaluateDialogQuality(string $userInput, string $response): float
    {
        $responseLength = strlen($response);
        $inputLength = strlen($userInput);

        // Base score from response quality
        $baseScore = match (true) {
            $responseLength < 20 => 4.0,
            $responseLength < 100 => 6.0,
            $responseLength < 300 => 8.0,
            default => 7.5, // Very long might be too verbose
        };

        // Bonus for appropriate response length relative to input
        $ratio = $responseLength / max(1, $inputLength);
        $ratioBonus = match (true) {
            $ratio > 0.5 && $ratio < 5.0 => 1.5, // Good ratio
            $ratio > 5.0 && $ratio < 10.0 => 0.5, // Acceptable
            default => 0.0,
        };

        return min(10.0, $baseScore + $ratioBonus);
    }

    /**
     * Override to customize task analysis for learning.
     */
    protected function analyzeTaskForLearning(string $task): array
    {
        $wordCount = str_word_count($task);
        $hasQuestion = str_contains($task, '?');

        return [
            'complexity' => match (true) {
                $wordCount > 30 => 'complex',
                $wordCount > 15 => 'medium',
                default => 'simple',
            },
            'domain' => 'conversation',
            'requires_tools' => false,
            'requires_knowledge' => false,
            'requires_reasoning' => false,
            'requires_iteration' => false,
            'requires_quality' => 'standard',
            'estimated_steps' => 1,
            'key_requirements' => $hasQuestion ? ['question_answering', 'context'] : ['conversation', 'context'],
        ];
    }

    /**
     * Override to evaluate dialog quality.
     */
    protected function evaluateResultQuality(AgentResult $result): float
    {
        if (!$result->isSuccess()) {
            return 0.0;
        }

        $answerLength = strlen($result->getAnswer());
        return match (true) {
            $answerLength < 20 => 4.0,
            $answerLength < 100 => 7.0,
            $answerLength < 300 => 8.5,
            default => 7.5,
        };
    }

    /**
     * Get agent identifier for learning traits.
     */
    protected function getAgentIdentifier(): string
    {
        return $this->name;
    }
}
