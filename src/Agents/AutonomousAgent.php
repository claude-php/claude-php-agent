<?php

declare(strict_types=1);

namespace ClaudeAgents\Agents;

use ClaudeAgents\AgentResult;
use ClaudeAgents\Contracts\AgentInterface;
use ClaudeAgents\Exceptions\ConfigurationException;
use ClaudeAgents\State\AgentState;
use ClaudeAgents\State\Goal;
use ClaudeAgents\State\StateManager;
use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Autonomous agent that pursues goals across multiple sessions.
 *
 * Maintains persistent state, tracks progress, and operates with safety limits.
 */
class AutonomousAgent implements AgentInterface
{
    private AgentState $state;
    private StateManager $stateManager;
    private string $name;
    private LoggerInterface $logger;
    private int $maxActionsPerSession;
    private int $actionsThisSession;

    /**
     * @param ClaudePhp $client Claude API client
     * @param array<string, mixed> $options Configuration:
     *   - goal: Goal description (required)
     *   - state_file: Path to state file (default: ./agent_state.json)
     *   - name: Agent name
     *   - max_sessions: Maximum allowed sessions
     *   - max_actions_per_session: Safety limit on actions per session
     *   - logger: PSR-3 logger
     */
    public function __construct(
        private readonly ClaudePhp $client,
        array $options = [],
    ) {
        $goalDescription = $options['goal'] ?? throw new ConfigurationException('Goal is required', 'goal');
        $stateFile = $options['state_file'] ?? './agent_state.json';
        $this->name = $options['name'] ?? 'autonomous_agent';
        $this->maxActionsPerSession = $options['max_actions_per_session'] ?? 50;
        $this->logger = $options['logger'] ?? new NullLogger();
        $this->actionsThisSession = 0;

        // Initialize state manager
        $this->stateManager = new StateManager($stateFile, ['logger' => $this->logger]);

        // Load or create state
        $this->state = $this->stateManager->load() ?? new AgentState(
            sessionNumber: 1,
            goal: new Goal($goalDescription),
        );

        $this->logger->info('Autonomous agent initialized', [
            'goal' => $goalDescription,
            'session' => $this->state->getSessionNumber(),
            'progress' => $this->state->getGoal()->getProgressPercentage(),
        ]);
    }

    /**
     * Run a single session of the agent.
     *
     * @param string $task Task or action to take
     * @return AgentResult Result of the session
     */
    public function runSession(string $task = ''): AgentResult
    {
        $this->logger->info("Starting autonomous agent session {$this->state->getSessionNumber()}");

        if ($this->state->getGoal()->isComplete()) {
            return AgentResult::success(
                answer: 'Goal already completed: ' . $this->state->getGoal()->getDescription(),
                messages: [],
                iterations: 0,
                metadata: [
                    'session_number' => $this->state->getSessionNumber(),
                    'goal_progress' => $this->state->getGoal()->getProgressPercentage(),
                    'already_complete' => true,
                ],
            );
        }

        $this->state->getGoal()->start();

        try {
            // Add task to conversation if provided
            if (! empty($task)) {
                $this->state->addMessage([
                    'role' => 'user',
                    'content' => $task,
                ]);
            }

            // Generate next action
            $systemPrompt = "You are an autonomous agent working toward a goal.\n\n" .
                           "Goal: {$this->state->getGoal()->getDescription()}\n\n" .
                           "Current progress: {$this->state->getGoal()->getProgressPercentage()}%\n\n" .
                           'You have completed these subgoals: ' .
                           (
                               empty($this->state->getGoal()->getCompletedSubgoals())
                               ? 'None yet'
                               : implode(', ', $this->state->getGoal()->getCompletedSubgoals())
                           ) . "\n\n" .
                           'Decide on the next action to take toward your goal. ' .
                           'Be specific and measurable. ' .
                           'Track completed subgoals as you go.';

            $response = $this->client->messages()->create([
                'model' => 'claude-sonnet-4-5',
                'max_tokens' => 1024,
                'system' => $systemPrompt,
                'messages' => $this->state->getConversationHistory(),
            ]);

            $answer = $this->extractTextContent($response->content ?? []);
            $this->state->addMessage([
                'role' => 'assistant',
                'content' => $answer,
            ]);

            // Record action
            $this->actionsThisSession++;
            $this->state->recordAction([
                'action' => $answer,
                'session' => $this->state->getSessionNumber(),
            ]);

            // Check if this was a goal completion action
            if ($this->isGoalCompletion($answer)) {
                $this->state->getGoal()->complete();
                $this->logger->info('Goal completed!');
            } else {
                // Update progress
                $progress = min(100, $this->state->getGoal()->getProgressPercentage() + 10);
                $this->state->getGoal()->setProgressPercentage($progress);
            }

            // Save state
            $this->stateManager->save($this->state);

            return AgentResult::success(
                answer: $answer,
                messages: $this->state->getConversationHistory(),
                iterations: 1,
                metadata: [
                    'session_number' => $this->state->getSessionNumber(),
                    'goal_progress' => $this->state->getGoal()->getProgressPercentage(),
                    'actions_this_session' => $this->actionsThisSession,
                    'goal_complete' => $this->state->getGoal()->isComplete(),
                ],
            );
        } catch (\Throwable $e) {
            $this->logger->error("Session failed: {$e->getMessage()}");

            // Save state even on failure
            $this->stateManager->save($this->state);

            return AgentResult::failure(
                error: $e->getMessage(),
                messages: $this->state->getConversationHistory(),
                metadata: [
                    'session_number' => $this->state->getSessionNumber(),
                    'goal_progress' => $this->state->getGoal()->getProgressPercentage(),
                ],
            );
        }
    }

    /**
     * Run multiple sessions until goal is complete or limit reached.
     *
     * @param int $maxSessions Maximum sessions to run
     * @return array<AgentResult> Results from each session
     */
    public function runUntilComplete(int $maxSessions = 10): array
    {
        $results = [];

        for ($i = 0; $i < $maxSessions; $i++) {
            if ($this->state->getGoal()->isComplete()) {
                $this->logger->info("Goal complete after {$i} sessions");

                break;
            }

            $result = $this->runSession('Continue working toward the goal.');
            $results[] = $result;

            if (! $result->isSuccess()) {
                $this->logger->warning('Session failed, stopping');

                break;
            }

            // Prepare for next session
            $this->state->incrementSession();
            $this->actionsThisSession = 0;
        }

        return $results;
    }

    /**
     * Get the current state.
     */
    public function getState(): AgentState
    {
        return $this->state;
    }

    /**
     * Get progress toward goal.
     */
    public function getProgress(): int
    {
        return $this->state->getGoal()->getProgressPercentage();
    }

    /**
     * Check if goal is complete.
     */
    public function isGoalComplete(): bool
    {
        return $this->state->getGoal()->isComplete();
    }

    /**
     * Get the goal description.
     */
    public function getGoal(): string
    {
        return $this->state->getGoal()->getDescription();
    }

    /**
     * Reset the agent state.
     */
    public function reset(): void
    {
        $this->logger->info('Resetting agent state');
        $this->stateManager->delete();
        $this->state = new AgentState(
            sessionNumber: 1,
            goal: new Goal($this->state->getGoal()->getDescription()),
        );
        $this->actionsThisSession = 0;
    }

    public function run(string $task): AgentResult
    {
        return $this->runSession($task);
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Check if response indicates goal completion.
     */
    private function isGoalCompletion(string $response): bool
    {
        $lowerResponse = strtolower($response);
        $indicators = ['completed', 'finished', 'done', 'achieved', 'accomplished'];

        foreach ($indicators as $indicator) {
            if (strpos($lowerResponse, $indicator) !== false) {
                return true;
            }
        }

        return false;
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
