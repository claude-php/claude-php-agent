<?php

declare(strict_types=1);

namespace ClaudeAgents\Agents;

use ClaudeAgents\AgentResult;
use ClaudeAgents\Contracts\AgentInterface;
use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Model-Based Agent - Maintains internal world model for informed decisions.
 *
 * Tracks environment state, predicts state transitions, and uses the model
 * to plan actions that achieve goals.
 */
class ModelBasedAgent implements AgentInterface
{
    private ClaudePhp $client;
    private string $name;
    private array $worldState = [];
    private array $stateHistory = [];
    private array $transitionRules = [];
    private ?string $goal = null;
    private LoggerInterface $logger;

    /**
     * @param ClaudePhp $client Claude API client
     * @param array<string, mixed> $options Configuration:
     *   - name: Agent name
     *   - initial_state: Initial world state
     *   - max_history: Maximum state history to keep (default: 100)
     *   - logger: PSR-3 logger
     */
    public function __construct(ClaudePhp $client, array $options = [])
    {
        $this->client = $client;
        $this->name = $options['name'] ?? 'model_based_agent';
        $this->worldState = $options['initial_state'] ?? [];
        $this->logger = $options['logger'] ?? new NullLogger();
    }

    public function run(string $task): AgentResult
    {
        $this->logger->info("Model-based agent: {$task}");

        try {
            // Extract goal or observation from task
            if ($this->isGoalStatement($task)) {
                $this->setGoal($task);
                $actions = $this->planActions();

                return AgentResult::success(
                    answer: 'Plan: ' . implode(' → ', $actions),
                    messages: [],
                    iterations: count($actions),
                    metadata: [
                        'goal' => $this->goal,
                        'current_state' => $this->worldState,
                        'planned_actions' => $actions,
                    ],
                );
            }
            // Treat as observation
            $this->updateStateFromObservation($task);

            return AgentResult::success(
                answer: 'State updated. Current state: ' . json_encode($this->worldState),
                messages: [],
                iterations: 1,
                metadata: [
                    'updated_state' => $this->worldState,
                    'state_history_size' => count($this->stateHistory),
                ],
            );

        } catch (\Throwable $e) {
            $this->logger->error("Model-based agent failed: {$e->getMessage()}");

            return AgentResult::failure(error: $e->getMessage());
        }
    }

    /**
     * Set the goal for the agent.
     */
    public function setGoal(string $goal): void
    {
        $this->goal = $goal;
        $this->logger->info("Goal set: {$goal}");
    }

    /**
     * Update world state.
     *
     * @param array<string, mixed> $state
     */
    public function updateState(array $state): void
    {
        $previousState = $this->worldState;
        $this->worldState = array_merge($this->worldState, $state);

        // Record transition
        $this->stateHistory[] = [
            'timestamp' => microtime(true),
            'previous' => $previousState,
            'current' => $this->worldState,
        ];

        // Limit history
        if (count($this->stateHistory) > 100) {
            array_shift($this->stateHistory);
        }

        $this->logger->debug('State updated', ['new_state' => $this->worldState]);
    }

    /**
     * Get current world state.
     *
     * @return array<string, mixed>
     */
    public function getState(): array
    {
        return $this->worldState;
    }

    /**
     * Add a state transition rule.
     *
     * @param string $action Action name
     * @param callable $transformer State transformer: fn(array $currentState): array $newState
     */
    public function addTransitionRule(string $action, callable $transformer): void
    {
        $this->transitionRules[$action] = $transformer;
        $this->logger->debug("Added transition rule for action: {$action}");
    }

    /**
     * Predict next state given an action.
     *
     * @return array<string, mixed>
     */
    public function predictNextState(string $action): array
    {
        if (isset($this->transitionRules[$action])) {
            return ($this->transitionRules[$action])($this->worldState);
        }

        // Use LLM to predict if no rule defined
        return $this->llmPredictState($action);
    }

    /**
     * Plan actions to achieve the goal.
     *
     * @return array<string>
     */
    private function planActions(): array
    {
        if (! $this->goal) {
            return [];
        }

        $prompt = $this->buildPlanningPrompt();

        try {
            $response = $this->client->messages()->create([
                'model' => 'claude-sonnet-4-5',
                'max_tokens' => 1024,
                'system' => 'You are a planning agent. Given a world state and goal, create a sequence of actions.',
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

            $plan = $this->extractTextContent($response->content ?? []);

            return $this->parseActions($plan);
        } catch (\Throwable $e) {
            $this->logger->error("Planning failed: {$e->getMessage()}");

            return [];
        }
    }

    /**
     * Update state from observation text.
     */
    private function updateStateFromObservation(string $observation): void
    {
        // Use LLM to extract state changes
        $prompt = <<<PROMPT
            Current state: {$this->formatState()}

            Observation: {$observation}

            Extract any state changes from this observation as a JSON object.
            Only include properties that changed. Return empty object {} if no changes.
            PROMPT;

        try {
            $response = $this->client->messages()->create([
                'model' => 'claude-sonnet-4-5',
                'max_tokens' => 512,
                'system' => 'Extract state changes from observations. Respond with JSON only.',
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

            $changes = $this->extractTextContent($response->content ?? []);
            $stateChanges = json_decode($changes, true);

            if (is_array($stateChanges) && ! empty($stateChanges)) {
                $this->updateState($stateChanges);
            }
        } catch (\Throwable $e) {
            $this->logger->warning("Failed to extract state from observation: {$e->getMessage()}");
        }
    }

    /**
     * Use LLM to predict state transition.
     *
     * @return array<string, mixed>
     */
    private function llmPredictState(string $action): array
    {
        $prompt = <<<PROMPT
            Current state: {$this->formatState()}

            Action to perform: {$action}

            Predict the resulting state after this action. Return as JSON.
            PROMPT;

        try {
            $response = $this->client->messages()->create([
                'model' => 'claude-sonnet-4-5',
                'max_tokens' => 512,
                'system' => 'Predict state transitions. Respond with JSON only.',
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

            $prediction = $this->extractTextContent($response->content ?? []);
            $predictedState = json_decode($prediction, true);

            return is_array($predictedState) ? $predictedState : $this->worldState;
        } catch (\Throwable $e) {
            $this->logger->warning("State prediction failed: {$e->getMessage()}");

            return $this->worldState;
        }
    }

    /**
     * Build planning prompt.
     */
    private function buildPlanningPrompt(): string
    {
        return <<<PROMPT
            Current World State:
            {$this->formatState()}

            Goal: {$this->goal}

            Create a sequence of actions to achieve this goal from the current state.
            List each action on a new line, numbered.
            PROMPT;
    }

    /**
     * Check if task is a goal statement.
     */
    private function isGoalStatement(string $task): bool
    {
        $goalKeywords = ['achieve', 'goal', 'reach', 'get to', 'make', 'plan'];
        $lower = strtolower($task);

        foreach ($goalKeywords as $keyword) {
            if (str_contains($lower, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Format state as readable string.
     */
    private function formatState(): string
    {
        return json_encode($this->worldState, JSON_PRETTY_PRINT);
    }

    /**
     * Parse actions from plan text.
     *
     * @return array<string>
     */
    private function parseActions(string $plan): array
    {
        $lines = explode("\n", $plan);
        $actions = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/^\d+\.\s*(.+)$/', $line, $matches)) {
                $actions[] = trim($matches[1]);
            } elseif (! empty($line) && ! str_starts_with($line, '#')) {
                $actions[] = $line;
            }
        }

        return $actions;
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

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get state history.
     *
     * @return array<array>
     */
    public function getStateHistory(): array
    {
        return $this->stateHistory;
    }
}
