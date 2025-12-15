<?php

declare(strict_types=1);

namespace ClaudeAgents\Agents;

use ClaudeAgents\AgentResult;
use ClaudeAgents\Contracts\AgentInterface;
use ClaudeAgents\Support\TextContentExtractor;
use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Utility-Based Agent - Selects actions by maximizing utility function.
 *
 * Evaluates actions based on utility scores, handles multi-objective
 * optimization, and makes trade-off decisions.
 */
class UtilityBasedAgent implements AgentInterface
{
    private ClaudePhp $client;
    private string $name;
    private $utilityFunction;
    private array $objectives = [];
    private array $constraints = [];
    private LoggerInterface $logger;

    /**
     * @param ClaudePhp $client Claude API client
     * @param array<string, mixed> $options Configuration:
     *   - name: Agent name
     *   - utility_function: Callable to compute utility: fn(array $action): float
     *   - objectives: Array of objective functions
     *   - constraints: Array of constraint functions
     *   - logger: PSR-3 logger
     */
    public function __construct(ClaudePhp $client, array $options = [])
    {
        $this->client = $client;
        $this->name = $options['name'] ?? 'utility_agent';
        $this->utilityFunction = $options['utility_function'] ?? fn ($action) => 0.0;
        $this->objectives = $options['objectives'] ?? [];
        $this->constraints = $options['constraints'] ?? [];
        $this->logger = $options['logger'] ?? new NullLogger();
    }

    public function run(string $task): AgentResult
    {
        $this->logger->info("Utility-based agent: {$task}");

        try {
            // Generate possible actions
            $actions = $this->generateActions($task);

            if (empty($actions)) {
                return AgentResult::failure(error: 'No valid actions generated');
            }

            // Evaluate utility for each action
            $evaluations = $this->evaluateActions($actions);

            // Select best action
            $bestAction = $this->selectBestAction($evaluations);

            return AgentResult::success(
                answer: $this->formatDecision($bestAction, $evaluations),
                messages: [],
                iterations: 1,
                metadata: [
                    'actions_evaluated' => count($actions),
                    'best_action' => $bestAction['action'],
                    'best_utility' => $bestAction['utility'],
                    'all_evaluations' => $evaluations,
                ],
            );
        } catch (\Throwable $e) {
            $this->logger->error("Utility agent failed: {$e->getMessage()}");

            return AgentResult::failure(error: $e->getMessage());
        }
    }

    /**
     * Set the utility function.
     *
     * @param callable $function Utility function: fn(array $action): float
     */
    public function setUtilityFunction(callable $function): void
    {
        $this->utilityFunction = $function;
    }

    /**
     * Add an objective function (for multi-objective optimization).
     *
     * @param string $name Objective name
     * @param callable $function Objective function: fn(array $action): float
     * @param float $weight Weight in [0,1] (default: 1.0)
     */
    public function addObjective(string $name, callable $function, float $weight = 1.0): void
    {
        $this->objectives[$name] = [
            'function' => $function,
            'weight' => $weight,
        ];

        $this->logger->debug("Added objective: {$name} (weight: {$weight})");
    }

    /**
     * Add a constraint.
     *
     * @param string $name Constraint name
     * @param callable $predicate Constraint predicate: fn(array $action): bool
     */
    public function addConstraint(string $name, callable $predicate): void
    {
        $this->constraints[$name] = $predicate;
        $this->logger->debug("Added constraint: {$name}");
    }

    /**
     * Generate possible actions from task.
     *
     * @return array<array>
     */
    private function generateActions(string $task): array
    {
        $prompt = <<<PROMPT
            Task: {$task}

            Generate 3-5 possible actions to accomplish this task.
            For each action, provide:
            - description: What the action does
            - estimated_value: Expected value/benefit (0-100)
            - estimated_cost: Expected cost/effort (0-100)
            - risk: Risk level (low/medium/high)

            Respond in JSON format as an array of action objects.
            PROMPT;

        try {
            $response = $this->client->messages()->create([
                'model' => 'claude-sonnet-4-5',
                'max_tokens' => 1024,
                'system' => 'Generate possible actions. Respond with JSON only.',
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

            $json = TextContentExtractor::extractFromResponse($response);
            $actions = json_decode($json, true);

            return is_array($actions) ? $actions : [];
        } catch (\Throwable $e) {
            $this->logger->error("Action generation failed: {$e->getMessage()}");

            return [];
        }
    }

    /**
     * Evaluate utility for all actions.
     *
     * @param array<array> $actions
     * @return array<array>
     */
    private function evaluateActions(array $actions): array
    {
        $evaluations = [];

        foreach ($actions as $action) {
            // Check constraints
            if (! $this->satisfiesConstraints($action)) {
                $this->logger->debug('Action filtered by constraints', ['action' => $action['description'] ?? 'unknown']);

                continue;
            }

            // Compute utility
            $utility = $this->computeUtility($action);

            // Compute multi-objective scores
            $objectiveScores = $this->computeObjectiveScores($action);

            $evaluations[] = [
                'action' => $action,
                'utility' => $utility,
                'objective_scores' => $objectiveScores,
            ];
        }

        // Sort by utility (descending)
        usort($evaluations, fn ($a, $b) => $b['utility'] <=> $a['utility']);

        return $evaluations;
    }

    /**
     * Check if action satisfies all constraints.
     */
    private function satisfiesConstraints(array $action): bool
    {
        foreach ($this->constraints as $name => $predicate) {
            if (! $predicate($action)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Compute utility for an action.
     */
    private function computeUtility(array $action): float
    {
        // Use single utility function if no objectives defined
        if (empty($this->objectives)) {
            return ($this->utilityFunction)($action);
        }

        // Multi-objective weighted sum
        $totalUtility = 0.0;
        $totalWeight = 0.0;

        foreach ($this->objectives as $name => $obj) {
            $score = ($obj['function'])($action);
            $weight = $obj['weight'];

            $totalUtility += $score * $weight;
            $totalWeight += $weight;
        }

        return $totalWeight > 0 ? $totalUtility / $totalWeight : 0.0;
    }

    /**
     * Compute scores for all objectives.
     *
     * @return array<string, float>
     */
    private function computeObjectiveScores(array $action): array
    {
        $scores = [];

        foreach ($this->objectives as $name => $obj) {
            $scores[$name] = ($obj['function'])($action);
        }

        return $scores;
    }

    /**
     * Select best action from evaluations.
     */
    private function selectBestAction(array $evaluations): array
    {
        if (empty($evaluations)) {
            return [
                'action' => ['description' => 'No valid actions'],
                'utility' => 0.0,
                'objective_scores' => [],
            ];
        }

        return $evaluations[0]; // Already sorted by utility
    }

    /**
     * Format decision output.
     */
    private function formatDecision(array $bestAction, array $allEvaluations): string
    {
        $output = "Utility-Based Decision\n";
        $output .= "======================\n\n";

        $action = $bestAction['action'];
        $output .= "Selected Action: {$action['description']}\n";
        $output .= "Utility Score: {$bestAction['utility']}\n";

        if (! empty($bestAction['objective_scores'])) {
            $output .= "\nObjective Scores:\n";
            foreach ($bestAction['objective_scores'] as $name => $score) {
                $output .= "  - {$name}: {$score}\n";
            }
        }

        $output .= "\nAction Details:\n";
        foreach ($action as $key => $value) {
            if ($key !== 'description') {
                $output .= "  - {$key}: {$value}\n";
            }
        }

        if (count($allEvaluations) > 1) {
            $output .= "\nAlternatives Considered: " . count($allEvaluations) . "\n";
        }

        return $output;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
