<?php

declare(strict_types=1);

namespace ClaudeAgents\Agents;

use ClaudeAgents\AgentResult;
use ClaudeAgents\Contracts\AgentInterface;
use ClaudeAgents\ML\Traits\LearnableAgent;
use ClaudeAgents\ML\Traits\StrategySelector;
use ClaudeAgents\Support\TextContentExtractor;
use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Coordinator Agent - Enhanced multi-agent coordination with ML-powered selection.
 *
 * Manages agent capabilities, resource allocation, conflict resolution,
 * and performance monitoring for complex multi-agent systems.
 *
 * Features:
 * - Automatic agent selection based on capabilities
 * - Load balancing across registered agents
 * - Task requirement analysis
 * - Performance tracking and workload monitoring
 * - **ML-Enhanced:** Learns optimal worker selection from historical performance
 * - **ML-Enhanced:** Learns decomposition strategies using StrategySelector
 *
 * @package ClaudeAgents\Agents
 */
class CoordinatorAgent implements AgentInterface
{
    use LearnableAgent;
    use StrategySelector;

    private ClaudePhp $client;
    private string $name;
    private string $model;
    private int $maxTokens;
    private array $agents = [];
    private array $workload = [];
    private array $performance = [];
    private LoggerInterface $logger;
    private bool $useMLSelection = false;

    /**
     * @param ClaudePhp $client The Claude API client
     * @param array<string, mixed> $options Configuration:
     *   - name: Coordinator name
     *   - model: Model to use for task analysis
     *   - max_tokens: Max tokens for task analysis
     *   - logger: PSR-3 logger
     *   - enable_ml_selection: Enable ML-based worker selection (default: false)
     *   - ml_history_path: Path for ML history storage
     */
    public function __construct(ClaudePhp $client, array $options = [])
    {
        $this->client = $client;
        $this->name = $options['name'] ?? 'coordinator_agent';
        $this->model = $options['model'] ?? 'claude-sonnet-4-5';
        $this->maxTokens = $options['max_tokens'] ?? 256;
        $this->logger = $options['logger'] ?? new NullLogger();
        $this->useMLSelection = $options['enable_ml_selection'] ?? false;

        // Enable ML features if requested
        if ($this->useMLSelection) {
            $historyPath = $options['ml_history_path'] ?? 'storage/coordinator_history.json';
            $this->enableLearning($historyPath);

            // Enable strategy learning for decomposition strategies
            $this->enableStrategyLearning(
                strategies: ['direct_delegation', 'parallel_decomposition', 'sequential_decomposition'],
                defaultStrategy: 'direct_delegation',
                historyPath: str_replace('.json', '_strategy.json', $historyPath)
            );
        }
    }

    public function run(string $task): AgentResult
    {
        $this->logger->info("Coordinator: {$task}");
        $startTime = microtime(true);

        try {
            // Analyze task requirements
            $requirements = $this->analyzeRequirements($task);

            // Select agent (ML-enhanced if enabled)
            $selectedAgent = $this->useMLSelection
                ? $this->selectAgentML($task, $requirements)
                : $this->selectAgent($requirements);

            if (! $selectedAgent) {
                return AgentResult::failure(
                    error: 'No suitable agent found for task requirements',
                    metadata: [
                        'requirements' => $requirements,
                        'available_agents' => array_keys($this->agents),
                    ]
                );
            }

            // Delegate to agent
            $this->logger->debug("Delegating to agent: {$selectedAgent}");
            $result = $this->delegateTask($selectedAgent, $task);

            // Track performance
            $duration = microtime(true) - $startTime;
            $this->trackPerformance($selectedAgent, $duration, true);

            // Update workload tracking
            $this->updateWorkload($selectedAgent);

            // Record for ML learning (if enabled)
            if ($this->useMLSelection && $this->isLearningEnabled()) {
                $agentResult = AgentResult::success(
                    answer: $result,
                    messages: [],
                    iterations: 1
                );
                $this->recordExecution($task, $agentResult, [
                    'duration' => $duration,
                    'selected_agent' => $selectedAgent,
                    'requirements' => $requirements,
                ]);
            }

            return AgentResult::success(
                answer: $result,
                messages: [],
                iterations: 1,
                metadata: [
                    'delegated_to' => $selectedAgent,
                    'requirements' => $requirements,
                    'workload' => $this->workload,
                    'duration' => round($duration, 3),
                    'agent_performance' => $this->performance[$selectedAgent] ?? [],
                    'ml_enabled' => $this->useMLSelection,
                ],
            );
        } catch (\Throwable $e) {
            $this->logger->error("Coordination failed: {$e->getMessage()}");

            return AgentResult::failure(
                error: $e->getMessage(),
                metadata: [
                    'task' => substr($task, 0, 100),
                ]
            );
        }
    }

    /**
     * Register an agent with the coordinator.
     *
     * @param string $id Unique identifier for the agent
     * @param AgentInterface $agent The agent instance
     * @param array<string> $capabilities List of capabilities this agent can handle
     */
    public function registerAgent(string $id, AgentInterface $agent, array $capabilities = []): void
    {
        $this->agents[$id] = [
            'agent' => $agent,
            'capabilities' => $capabilities,
        ];
        $this->workload[$id] = 0;
        $this->performance[$id] = [
            'total_tasks' => 0,
            'successful_tasks' => 0,
            'average_duration' => 0,
        ];
        $this->logger->info("Registered agent: {$id}", [
            'capabilities' => $capabilities,
        ]);
    }

    /**
     * Get registered agent IDs.
     *
     * @return array<string>
     */
    public function getAgentIds(): array
    {
        return array_keys($this->agents);
    }

    /**
     * Get agent capabilities.
     *
     * @return array<string>
     */
    public function getAgentCapabilities(string $id): array
    {
        return $this->agents[$id]['capabilities'] ?? [];
    }

    /**
     * Get current workload distribution.
     *
     * @return array<string, int>
     */
    public function getWorkload(): array
    {
        return $this->workload;
    }

    /**
     * Get performance metrics for all agents.
     *
     * @return array<string, array>
     */
    public function getPerformance(): array
    {
        return $this->performance;
    }

    /**
     * Analyze task requirements to determine needed capabilities.
     *
     * @return array<string>
     */
    private function analyzeRequirements(string $task): array
    {
        try {
            $response = $this->client->messages()->create([
                'model' => $this->model,
                'max_tokens' => $this->maxTokens,
                'system' => 'Analyze task requirements. Respond with JSON array of required capabilities. ' .
                    'Examples: ["coding", "testing", "documentation", "research", "analysis", "writing"]',
                'messages' => [['role' => 'user', 'content' => "Task: {$task}\n\nWhat capabilities are needed? Return only a JSON array."]],
            ]);

            $json = TextContentExtractor::extractFromResponse($response);

            // Try to extract JSON from the response
            if (preg_match('/\[.*?\]/s', $json, $matches)) {
                $decoded = json_decode($matches[0], true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }

            // Fallback: extract keywords from task
            return $this->extractKeywords($task);
        } catch (\Throwable $e) {
            $this->logger->warning('Requirement analysis failed, using keyword extraction', [
                'error' => $e->getMessage(),
            ]);

            return $this->extractKeywords($task);
        }
    }

    /**
     * Extract keywords from task as fallback requirement analysis.
     *
     * @return array<string>
     */
    private function extractKeywords(string $task): array
    {
        $keywords = [
            'code' => ['coding', 'implementation'],
            'test' => ['testing', 'quality assurance'],
            'write' => ['writing', 'documentation'],
            'research' => ['research', 'information gathering'],
            'analyze' => ['analysis', 'evaluation'],
            'design' => ['design', 'architecture'],
        ];

        $found = [];
        $taskLower = strtolower($task);

        foreach ($keywords as $word => $capabilities) {
            if (strpos($taskLower, $word) !== false) {
                $found = array_merge($found, $capabilities);
            }
        }

        return ! empty($found) ? array_unique($found) : ['general'];
    }

    private function selectAgent(array $requirements): ?string
    {
        $candidates = [];

        foreach ($this->agents as $id => $info) {
            $matchCount = count(array_intersect($requirements, $info['capabilities']));
            if ($matchCount > 0) {
                $candidates[$id] = [
                    'match_score' => $matchCount,
                    'workload' => $this->workload[$id],
                ];
            }
        }

        if (empty($candidates)) {
            return null;
        }

        // Select based on match score and workload (load balancing)
        uasort($candidates, function ($a, $b) {
            if ($a['match_score'] !== $b['match_score']) {
                return $b['match_score'] <=> $a['match_score'];
            }

            return $a['workload'] <=> $b['workload'];
        });

        return array_key_first($candidates);
    }

    private function delegateTask(string $agentId, string $task): string
    {
        $agent = $this->agents[$agentId]['agent'];
        $result = $agent->run($task);

        return $result->getAnswer();
    }

    private function updateWorkload(string $agentId): void
    {
        $this->workload[$agentId]++;
    }

    /**
     * Track agent performance metrics.
     */
    private function trackPerformance(string $agentId, float $duration, bool $success): void
    {
        if (! isset($this->performance[$agentId])) {
            $this->performance[$agentId] = [
                'total_tasks' => 0,
                'successful_tasks' => 0,
                'average_duration' => 0,
            ];
        }

        $perf = &$this->performance[$agentId];
        $perf['total_tasks']++;

        if ($success) {
            $perf['successful_tasks']++;
        }

        // Update running average
        $totalTasks = $perf['total_tasks'];
        $oldAvg = $perf['average_duration'];
        $perf['average_duration'] = ($oldAvg * ($totalTasks - 1) + $duration) / $totalTasks;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * ML-enhanced agent selection based on historical performance.
     *
     * @param string $task The task to delegate
     * @param array<string> $requirements Task requirements
     * @return string|null Selected agent ID
     */
    private function selectAgentML(string $task, array $requirements): ?string
    {
        // Get historical performance for similar tasks
        $similar = $this->getHistoricalPerformance($task, k: 10);

        if (empty($similar)) {
            // No history yet, fall back to rule-based selection
            return $this->selectAgent($requirements);
        }

        // Score agents based on historical success with similar tasks
        $agentScores = [];

        foreach ($this->agents as $agentId => $info) {
            // Base score from capability matching
            $capabilityMatch = count(array_intersect($requirements, $info['capabilities']));

            if ($capabilityMatch === 0) {
                continue; // Can't handle this task
            }

            $baseScore = $capabilityMatch * 10;

            // Enhance with historical performance
            $historicalSuccess = 0;
            $historicalCount = 0;

            foreach ($similar as $record) {
                if (($record['metadata']['selected_agent'] ?? null) === $agentId) {
                    $historicalCount++;
                    if ($record['metadata']['success'] ?? $record['success'] ?? false) {
                        $historicalSuccess++;
                    }
                }
            }

            if ($historicalCount > 0) {
                $successRate = $historicalSuccess / $historicalCount;
                $historicalBonus = $successRate * 20;
            } else {
                $historicalBonus = 10; // Neutral for untried agents
            }

            // Load balancing factor
            $loadPenalty = $this->workload[$agentId] * 2;

            $totalScore = $baseScore + $historicalBonus - $loadPenalty;
            $agentScores[$agentId] = $totalScore;

            $this->logger->debug("Agent {$agentId} score: {$totalScore} " .
                "(capability: {$capabilityMatch}, historical: {$historicalBonus}, load: -{$loadPenalty})");
        }

        if (empty($agentScores)) {
            return null;
        }

        // Select highest scoring agent
        arsort($agentScores);
        return array_key_first($agentScores);
    }

    /**
     * Override to customize task analysis for learning.
     *
     * @param string $task Task to analyze
     * @return array Task analysis
     */
    protected function analyzeTaskForLearning(string $task): array
    {
        $requirements = $this->analyzeRequirements($task);

        return [
            'complexity' => count($requirements) > 3 ? 'complex' : (count($requirements) > 1 ? 'medium' : 'simple'),
            'domain' => 'coordination',
            'requires_tools' => false,
            'requires_knowledge' => true,
            'requires_reasoning' => true,
            'requires_iteration' => false,
            'requires_quality' => 'standard',
            'estimated_steps' => 3,
            'key_requirements' => $requirements,
        ];
    }

    /**
     * Override to evaluate coordination quality.
     *
     * @param AgentResult $result Result to evaluate
     * @return float Quality score (0-10)
     */
    protected function evaluateResultQuality(AgentResult $result): float
    {
        if (!$result->isSuccess()) {
            return 0.0;
        }

        // Base score on success and answer quality
        $answerLength = strlen($result->getAnswer());

        if ($answerLength < 20) {
            return 5.0; // Very short answer
        } elseif ($answerLength < 100) {
            return 7.0;
        } else {
            return 8.5; // Good detailed answer
        }
    }

    /**
     * Get agent identifier for learning traits.
     *
     * @return string
     */
    protected function getAgentIdentifier(): string
    {
        return $this->name;
    }
}
