<?php

declare(strict_types=1);

namespace ClaudeAgents\MultiAgent;

use function Amp\async;

use Amp\Future;

use function Amp\Future\await;
use function Amp\Future\awaitAll;

use ClaudeAgents\AgentResult;
use ClaudeAgents\Contracts\AgentInterface;
use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Async multi-agent collaboration manager using AMPHP.
 *
 * Enables parallel execution of multiple agents for faster collaboration.
 */
class AsyncCollaborationManager
{
    private ClaudePhp $client;
    private array $agents = [];
    private ?SharedMemory $sharedMemory = null;
    private LoggerInterface $logger;
    private int $maxConcurrent;

    /**
     * @param ClaudePhp $client Claude API client
     * @param array<string, mixed> $options Configuration:
     *   - shared_memory: SharedMemory instance
     *   - max_concurrent: Maximum parallel agents (default: 5)
     *   - logger: PSR-3 logger
     */
    public function __construct(ClaudePhp $client, array $options = [])
    {
        $this->client = $client;
        $this->sharedMemory = $options['shared_memory'] ?? new SharedMemory();
        $this->maxConcurrent = $options['max_concurrent'] ?? 5;
        $this->logger = $options['logger'] ?? new NullLogger();
    }

    /**
     * Register an agent.
     */
    public function registerAgent(string $id, AgentInterface $agent, array $capabilities = []): void
    {
        $this->agents[$id] = [
            'agent' => $agent,
            'capabilities' => $capabilities,
        ];

        $this->logger->info("Registered agent: {$id}", ['capabilities' => $capabilities]);
    }

    /**
     * Execute multiple agents in parallel.
     *
     * @param array<string, string> $tasks Map of agent ID to task
     * @return array<string, AgentResult>
     */
    public function executeParallel(array $tasks): array
    {
        $this->logger->info('Starting parallel execution', [
            'agent_count' => count($tasks),
        ]);

        $futures = [];

        foreach ($tasks as $agentId => $task) {
            if (! isset($this->agents[$agentId])) {
                $this->logger->warning("Agent not found: {$agentId}");

                continue;
            }

            $futures[$agentId] = async(function () use ($agentId, $task) {
                return $this->executeAgent($agentId, $task);
            });
        }

        // Wait for all to complete
        $results = awaitAll($futures);

        $this->logger->info('Parallel execution complete', [
            'completed' => count($results),
        ]);

        return $results;
    }

    /**
     * Execute agents in parallel with batching.
     *
     * @param array<string, string> $tasks
     * @return array<string, AgentResult>
     */
    public function executeBatched(array $tasks): array
    {
        $batches = array_chunk($tasks, $this->maxConcurrent, true);
        $allResults = [];

        foreach ($batches as $batchNum => $batch) {
            $this->logger->info('Processing batch ' . ($batchNum + 1) . '/' . count($batches));

            $batchResults = $this->executeParallel($batch);
            $allResults = array_merge($allResults, $batchResults);
        }

        return $allResults;
    }

    /**
     * Collaborate with agents working in parallel on subtasks.
     *
     * @param string $task Main task
     * @param int $parallelAgents Number of agents to use in parallel
     * @return AgentResult
     */
    public function collaborateParallel(string $task, int $parallelAgents = 3): AgentResult
    {
        $this->logger->info('Starting parallel collaboration');

        try {
            // Step 1: Decompose task into subtasks
            $subtasks = $this->decomposeTask($task, $parallelAgents);

            // Step 2: Execute subtasks in parallel
            $agentIds = array_slice(array_keys($this->agents), 0, $parallelAgents);
            $taskMap = array_combine($agentIds, $subtasks);

            $results = $this->executeParallel($taskMap);

            // Step 3: Synthesize results
            $synthesis = $this->synthesizeResults($results);

            return AgentResult::success(
                answer: $synthesis,
                messages: [],
                iterations: 1,
                metadata: [
                    'parallel_agents' => $parallelAgents,
                    'subtasks_completed' => count($results),
                    'agents_used' => array_keys($results),
                ]
            );
        } catch (\Throwable $e) {
            $this->logger->error("Parallel collaboration failed: {$e->getMessage()}");

            return AgentResult::failure(error: $e->getMessage());
        }
    }

    /**
     * Race multiple agents - first one to complete wins.
     *
     * @param array<string, string> $tasks
     * @return array{agent_id: string, result: AgentResult}
     */
    public function race(array $tasks): array
    {
        $this->logger->info('Starting agent race', ['agents' => count($tasks)]);

        $futures = [];

        foreach ($tasks as $agentId => $task) {
            if (! isset($this->agents[$agentId])) {
                continue;
            }

            $futures[$agentId] = async(function () use ($agentId, $task) {
                return [
                    'agent_id' => $agentId,
                    'result' => $this->executeAgent($agentId, $task),
                ];
            });
        }

        // Wait for first to complete
        $winner = await(Future\awaitFirst($futures));

        $this->logger->info("Race won by: {$winner['agent_id']}");

        return $winner;
    }

    /**
     * Execute an agent with a task.
     */
    private function executeAgent(string $agentId, string $task): AgentResult
    {
        $agent = $this->agents[$agentId]['agent'];
        $this->logger->debug("Executing agent: {$agentId}");

        $startTime = microtime(true);
        $result = $agent->run($task);
        $duration = microtime(true) - $startTime;

        $this->logger->debug('Agent completed', [
            'agent' => $agentId,
            'duration' => round($duration, 3),
            'success' => $result->isSuccess(),
        ]);

        return $result;
    }

    /**
     * Decompose a task into subtasks for parallel execution.
     *
     * @return array<string>
     */
    private function decomposeTask(string $task, int $count): array
    {
        $response = $this->client->messages()->create([
            'model' => 'claude-sonnet-4-5',
            'max_tokens' => 1024,
            'system' => "Decompose tasks into {$count} parallel subtasks. Return as JSON array.",
            'messages' => [
                [
                    'role' => 'user',
                    'content' => "Break this task into {$count} independent subtasks that can be done in parallel:\n\n{$task}\n\nReturn JSON array only.",
                ],
            ],
        ]);

        $json = $this->extractTextContent($response->content ?? []);

        // Try to extract JSON array
        if (preg_match('/\[.*?\]/s', $json, $matches)) {
            $subtasks = json_decode($matches[0], true);
            if (is_array($subtasks) && count($subtasks) > 0) {
                return array_slice($subtasks, 0, $count);
            }
        }

        // Fallback: simple division
        return array_fill(0, $count, $task);
    }

    /**
     * Synthesize results from multiple agents.
     */
    private function synthesizeResults(array $results): string
    {
        $combined = '';
        foreach ($results as $agentId => $result) {
            if ($result->isSuccess()) {
                $combined .= "[{$agentId}] {$result->getAnswer()}\n\n";
            }
        }

        $response = $this->client->messages()->create([
            'model' => 'claude-sonnet-4-5',
            'max_tokens' => 2048,
            'system' => 'Synthesize results from multiple agents into a coherent final answer.',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => "Synthesize these parallel results:\n\n{$combined}",
                ],
            ],
        ]);

        return $this->extractTextContent($response->content ?? []);
    }

    /**
     * Get shared memory instance.
     */
    public function getSharedMemory(): SharedMemory
    {
        return $this->sharedMemory;
    }

    /**
     * Extract text content from response.
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
