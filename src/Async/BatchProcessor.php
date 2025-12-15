<?php

declare(strict_types=1);

namespace ClaudeAgents\Async;

use function Amp\async;

use Amp\Future;
use ClaudeAgents\Agent;
use ClaudeAgents\AgentResult;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Processes multiple agent tasks in batches using AMPHP.
 *
 * This provides concurrent execution of agent tasks, allowing
 * multiple agents to work in parallel.
 */
class BatchProcessor
{
    /**
     * @var array<string, string> Map of task ID to task description
     */
    private array $tasks = [];

    /**
     * @var array<string, AgentResult> Completed task results
     */
    private array $results = [];

    private LoggerInterface $logger;

    /**
     * @param Agent $agent The agent to use for processing
     */
    public function __construct(
        private readonly Agent $agent,
        array $options = [],
    ) {
        $this->logger = $options['logger'] ?? new NullLogger();
    }

    /**
     * Create a new batch processor.
     */
    public static function create(Agent $agent, array $options = []): self
    {
        return new self($agent, $options);
    }

    /**
     * Add a task to the batch.
     *
     * @param string $id Unique task identifier
     * @param string $description Task description
     * @return self
     */
    public function add(string $id, string $description): self
    {
        $this->tasks[$id] = $description;

        return $this;
    }

    /**
     * Add multiple tasks.
     *
     * @param array<string, string> $tasks Map of ID to description
     * @return self
     */
    public function addMany(array $tasks): self
    {
        foreach ($tasks as $id => $description) {
            $this->add($id, $description);
        }

        return $this;
    }

    /**
     * Process all tasks with true concurrent execution.
     *
     * @param int $concurrency Maximum concurrent tasks
     * @return array<string, AgentResult> Results mapped by task ID
     */
    public function run(int $concurrency = 3): array
    {
        $this->logger->info('Processing ' . count($this->tasks) . " tasks with concurrency: {$concurrency}");

        // Split tasks into batches based on concurrency
        $taskIds = array_keys($this->tasks);
        $batches = array_chunk($taskIds, $concurrency, true);

        foreach ($batches as $batchIds) {
            $this->processBatch($batchIds);
        }

        $this->logger->info('Batch processing complete');

        return $this->results;
    }

    /**
     * Process a batch of tasks concurrently.
     *
     * @param array<string> $taskIds Task IDs to process
     */
    private function processBatch(array $taskIds): void
    {
        $futures = [];

        foreach ($taskIds as $id) {
            $task = $this->tasks[$id];
            $futures[$id] = $this->processTaskAsync($id, $task);
        }

        // Wait for all tasks in the batch to complete
        foreach ($futures as $id => $future) {
            $this->results[$id] = $future->await();
        }
    }

    /**
     * Process a single task asynchronously.
     *
     * @param string $id Task ID
     * @param string $task Task description
     * @return Future<AgentResult>
     */
    private function processTaskAsync(string $id, string $task): Future
    {
        return async(function () use ($id, $task) {
            $this->logger->debug("Processing task: {$id}");

            try {
                $result = $this->agent->run($task);
                $this->logger->debug("Task {$id} completed successfully");

                return $result;
            } catch (\Throwable $e) {
                $this->logger->error("Task {$id} failed: {$e->getMessage()}");

                return AgentResult::failure(
                    error: $e->getMessage(),
                );
            }
        });
    }

    /**
     * Process all tasks and return promises for each.
     *
     * @return array<string, Promise> Promises mapped by task ID
     */
    public function runAsync(): array
    {
        $promises = [];

        foreach ($this->tasks as $id => $task) {
            $promise = new Promise();

            $future = $this->processTaskAsync($id, $task);

            async(function () use ($future, $promise, $id) {
                try {
                    $result = $future->await();
                    $this->results[$id] = $result;
                    $promise->resolve($result);
                } catch (\Throwable $e) {
                    $promise->reject($e);
                }
            });

            $promises[$id] = $promise;
        }

        return $promises;
    }

    /**
     * Get results for a specific task.
     */
    public function getResult(string $id): ?AgentResult
    {
        return $this->results[$id] ?? null;
    }

    /**
     * Get all results.
     *
     * @return array<string, AgentResult>
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * Get successful results.
     *
     * @return array<string, AgentResult>
     */
    public function getSuccessful(): array
    {
        return array_filter(
            $this->results,
            fn ($r) => $r->isSuccess()
        );
    }

    /**
     * Get failed results.
     *
     * @return array<string, AgentResult>
     */
    public function getFailed(): array
    {
        return array_filter(
            $this->results,
            fn ($r) => ! $r->isSuccess()
        );
    }

    /**
     * Get batch statistics.
     *
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        $successful = count($this->getSuccessful());
        $failed = count($this->getFailed());
        $total = count($this->results);

        $totalTokens = ['input' => 0, 'output' => 0];
        foreach ($this->results as $result) {
            $usage = $result->getTokenUsage();
            $totalTokens['input'] += $usage['input'] ?? 0;
            $totalTokens['output'] += $usage['output'] ?? 0;
        }

        return [
            'total_tasks' => $total,
            'successful' => $successful,
            'failed' => $failed,
            'success_rate' => $total > 0 ? $successful / $total : 0,
            'total_tokens' => [
                'input' => $totalTokens['input'],
                'output' => $totalTokens['output'],
                'total' => $totalTokens['input'] + $totalTokens['output'],
            ],
        ];
    }

    /**
     * Reset the processor state.
     */
    public function reset(): void
    {
        $this->tasks = [];
        $this->results = [];
    }
}
