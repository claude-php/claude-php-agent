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
 * Coordinator Agent - Enhanced multi-agent coordination with load balancing.
 *
 * Manages agent capabilities, resource allocation, conflict resolution,
 * and performance monitoring for complex multi-agent systems.
 *
 * Features:
 * - Automatic agent selection based on capabilities
 * - Load balancing across registered agents
 * - Task requirement analysis
 * - Performance tracking and workload monitoring
 */
class CoordinatorAgent implements AgentInterface
{
    private ClaudePhp $client;
    private string $name;
    private string $model;
    private int $maxTokens;
    private array $agents = [];
    private array $workload = [];
    private array $performance = [];
    private LoggerInterface $logger;

    /**
     * @param ClaudePhp $client The Claude API client
     * @param array<string, mixed> $options Configuration:
     *   - name: Coordinator name
     *   - model: Model to use for task analysis
     *   - max_tokens: Max tokens for task analysis
     *   - logger: PSR-3 logger
     */
    public function __construct(ClaudePhp $client, array $options = [])
    {
        $this->client = $client;
        $this->name = $options['name'] ?? 'coordinator_agent';
        $this->model = $options['model'] ?? 'claude-sonnet-4-5';
        $this->maxTokens = $options['max_tokens'] ?? 256;
        $this->logger = $options['logger'] ?? new NullLogger();
    }

    public function run(string $task): AgentResult
    {
        $this->logger->info("Coordinator: {$task}");
        $startTime = microtime(true);

        try {
            // Analyze task requirements
            $requirements = $this->analyzeRequirements($task);

            // Match to capable agents
            $selectedAgent = $this->selectAgent($requirements);

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
}
