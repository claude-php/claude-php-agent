<?php

declare(strict_types=1);

namespace ClaudeAgents\Agents;

use ClaudeAgents\AgentResult;
use ClaudeAgents\Contracts\AgentInterface;
use ClaudeAgents\Scheduling\Schedule;
use ClaudeAgents\Scheduling\Task;
use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Scheduler Agent - Manages timed tasks and dependencies.
 *
 * Handles cron-style scheduling, one-time tasks, task dependencies,
 * and execution history.
 */
class SchedulerAgent implements AgentInterface
{
    private ClaudePhp $client;
    private string $name;
    private array $tasks = [];
    private array $executionHistory = [];
    private LoggerInterface $logger;
    private bool $isRunning = false;

    /**
     * @param ClaudePhp $client Claude API client
     * @param array<string, mixed> $options Configuration:
     *   - name: Agent name
     *   - timezone: Default timezone (default: UTC)
     *   - logger: PSR-3 logger
     */
    public function __construct(ClaudePhp $client, array $options = [])
    {
        $this->client = $client;
        $this->name = $options['name'] ?? 'scheduler_agent';
        $this->logger = $options['logger'] ?? new NullLogger();
    }

    public function run(string $task): AgentResult
    {
        // Execute pending tasks
        $this->logger->info("Scheduler agent: {$task}");

        try {
            $executed = $this->runPendingTasks();

            return AgentResult::success(
                answer: "Executed {$executed} pending tasks",
                messages: [],
                iterations: 1,
                metadata: [
                    'tasks_executed' => $executed,
                    'total_tasks' => count($this->tasks),
                    'execution_history' => array_slice($this->executionHistory, -10),
                ],
            );
        } catch (\Throwable $e) {
            $this->logger->error("Scheduler failed: {$e->getMessage()}");

            return AgentResult::failure(error: $e->getMessage());
        }
    }

    /**
     * Schedule a recurring task with cron expression.
     */
    public function schedule(string $name, string $cronExpression, callable $callback, array $dependencies = []): Task
    {
        $schedule = Schedule::cron($cronExpression);
        $task = new Task($name, $callback, $schedule, $dependencies);

        $this->tasks[$task->getId()] = $task;
        $this->logger->info("Scheduled recurring task: {$name} ({$cronExpression})");

        return $task;
    }

    /**
     * Schedule a one-time task at a specific time.
     */
    public function scheduleAt(string $name, float $timestamp, callable $callback, array $dependencies = []): Task
    {
        $schedule = Schedule::at($timestamp);
        $task = new Task($name, $callback, $schedule, $dependencies);

        $this->tasks[$task->getId()] = $task;
        $this->logger->info("Scheduled one-time task: {$name} at " . date('Y-m-d H:i:s', (int)$timestamp));

        return $task;
    }

    /**
     * Schedule a one-time task with relative time.
     */
    public function scheduleOnce(string $name, string $relativeTime, callable $callback, array $dependencies = []): Task
    {
        $schedule = Schedule::in($relativeTime);
        $task = new Task($name, $callback, $schedule, $dependencies);

        $this->tasks[$task->getId()] = $task;
        $this->logger->info("Scheduled one-time task: {$name} in {$relativeTime}");

        return $task;
    }

    /**
     * Schedule a recurring task with interval.
     */
    public function scheduleEvery(string $name, int $seconds, callable $callback, array $dependencies = []): Task
    {
        $schedule = Schedule::every($seconds);
        $task = new Task($name, $callback, $schedule, $dependencies);

        $this->tasks[$task->getId()] = $task;
        $this->logger->info("Scheduled recurring task: {$name} every {$seconds}s");

        return $task;
    }

    /**
     * Remove a scheduled task.
     */
    public function unschedule(string $taskId): bool
    {
        if (isset($this->tasks[$taskId])) {
            unset($this->tasks[$taskId]);
            $this->logger->info("Unscheduled task: {$taskId}");

            return true;
        }

        return false;
    }

    /**
     * Start the scheduler loop.
     */
    public function start(int $checkInterval = 1): void
    {
        $this->logger->info('Starting scheduler loop');
        $this->isRunning = true;

        while ($this->isRunning) {
            $this->runPendingTasks();
            sleep($checkInterval);
        }
    }

    /**
     * Stop the scheduler loop.
     */
    public function stop(): void
    {
        $this->isRunning = false;
        $this->logger->info('Scheduler stopped');
    }

    /**
     * Run all pending tasks that are due.
     */
    private function runPendingTasks(): int
    {
        $currentTime = microtime(true);
        $executed = 0;

        // Get tasks that are due
        $dueTasks = array_filter($this->tasks, fn ($task) => $task->isDue($currentTime));

        // Resolve dependencies and execute
        $executedIds = [];

        foreach ($dueTasks as $task) {
            if ($this->canExecute($task, $executedIds)) {
                $this->executeTask($task);
                $executedIds[] = $task->getId();
                $executed++;
            }
        }

        return $executed;
    }

    /**
     * Check if task can be executed (dependencies satisfied).
     */
    private function canExecute(Task $task, array $executedIds): bool
    {
        $dependencies = $task->getDependencies();

        if (empty($dependencies)) {
            return true;
        }

        // Check if all dependencies have been executed
        foreach ($dependencies as $depId) {
            if (! in_array($depId, $executedIds) && isset($this->tasks[$depId])) {
                // Dependency not yet executed
                return false;
            }
        }

        return true;
    }

    /**
     * Execute a single task.
     */
    private function executeTask(Task $task): void
    {
        $startTime = microtime(true);

        try {
            $this->logger->info("Executing task: {$task->getName()}");

            $result = $task->execute();
            $duration = microtime(true) - $startTime;

            $this->recordExecution($task, true, null, $duration);

            $this->logger->info("Task completed: {$task->getName()} ({$duration}s)");
        } catch (\Throwable $e) {
            $duration = microtime(true) - $startTime;

            $this->recordExecution($task, false, $e->getMessage(), $duration);
            $this->logger->error("Task failed: {$task->getName()} - {$e->getMessage()}");
        }
    }

    /**
     * Record task execution in history.
     */
    private function recordExecution(Task $task, bool $success, ?string $error, float $duration): void
    {
        $this->executionHistory[] = [
            'task_id' => $task->getId(),
            'task_name' => $task->getName(),
            'timestamp' => microtime(true),
            'success' => $success,
            'error' => $error,
            'duration' => $duration,
        ];

        // Keep only last 1000 executions
        if (count($this->executionHistory) > 1000) {
            array_shift($this->executionHistory);
        }
    }

    /**
     * Get all scheduled tasks.
     *
     * @return array<Task>
     */
    public function getTasks(): array
    {
        return array_values($this->tasks);
    }

    /**
     * Get task by ID.
     */
    public function getTask(string $taskId): ?Task
    {
        return $this->tasks[$taskId] ?? null;
    }

    /**
     * Get execution history.
     *
     * @return array<array>
     */
    public function getExecutionHistory(int $limit = 100): array
    {
        return array_slice($this->executionHistory, -$limit);
    }

    public function getName(): string
    {
        return $this->name;
    }
}
