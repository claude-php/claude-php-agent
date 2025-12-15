<?php

declare(strict_types=1);

namespace ClaudeAgents\Agents;

use ClaudeAgents\AgentResult;
use ClaudeAgents\Contracts\AgentInterface;
use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Task Prioritization Agent - BabyAGI-style task generation and prioritization.
 *
 * Dynamically generates subtasks, prioritizes them, manages task queue,
 * and tracks dependencies and progress toward a goal.
 */
class TaskPrioritizationAgent implements AgentInterface
{
    private ClaudePhp $client;
    private string $name;
    private string $goal;
    private array $taskQueue = [];
    private array $completedTasks = [];
    private LoggerInterface $logger;

    public function __construct(ClaudePhp $client, array $options = [])
    {
        $this->client = $client;
        $this->name = $options['name'] ?? 'task_prioritization_agent';
        $this->goal = $options['goal'] ?? '';
        $this->logger = $options['logger'] ?? new NullLogger();
    }

    public function run(string $task): AgentResult
    {
        $this->goal = $task;
        $this->logger->info("Task prioritization for goal: {$task}");

        try {
            // Generate initial tasks
            $this->generateTasks();

            // Execute tasks in priority order
            $results = [];
            $iteration = 0;
            $maxIterations = 20;

            while (! empty($this->taskQueue) && $iteration < $maxIterations) {
                $iteration++;

                // Get highest priority task
                $currentTask = array_shift($this->taskQueue);

                // Execute task
                $result = $this->executeTask($currentTask);
                $results[] = $result;

                // Mark as completed
                $this->completedTasks[] = $currentTask;

                // Generate new tasks based on result (if needed)
                if ($iteration < $maxIterations - 5) {
                    $this->generateAdditionalTasks($result);
                }

                // Re-prioritize queue
                $this->prioritizeTasks();
            }

            return AgentResult::success(
                answer: $this->formatResults($results),
                messages: [],
                iterations: $iteration,
                metadata: [
                    'goal' => $this->goal,
                    'tasks_completed' => count($this->completedTasks),
                    'tasks_remaining' => count($this->taskQueue),
                ],
            );
        } catch (\Throwable $e) {
            $this->logger->error("Task prioritization failed: {$e->getMessage()}");

            return AgentResult::failure(error: $e->getMessage());
        }
    }

    private function generateTasks(): void
    {
        $prompt = <<<PROMPT
            Goal: {$this->goal}

            Generate 5-7 specific, actionable tasks to achieve this goal.
            For each task provide: description, priority (1-10), estimated_effort (1-5)
            Format as JSON array.
            PROMPT;

        $response = $this->client->messages()->create([
            'model' => 'claude-sonnet-4-5',
            'max_tokens' => 1024,
            'system' => 'Generate prioritized task lists. Respond with JSON only.',
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        $json = $this->extractTextContent($response->content ?? []);
        $tasks = json_decode($json, true);

        if (is_array($tasks)) {
            $this->taskQueue = array_merge($this->taskQueue, $tasks);
            $this->prioritizeTasks();
        }
    }

    private function executeTask(array $task): string
    {
        $this->logger->info("Executing: {$task['description']}");

        $response = $this->client->messages()->create([
            'model' => 'claude-sonnet-4-5',
            'max_tokens' => 512,
            'system' => "You are executing subtasks toward the goal: {$this->goal}",
            'messages' => [['role' => 'user', 'content' => $task['description']]],
        ]);

        return $this->extractTextContent($response->content ?? []);
    }

    private function generateAdditionalTasks(string $lastResult): void
    {
        $completedStr = implode(', ', array_column($this->completedTasks, 'description'));

        $prompt = <<<PROMPT
            Goal: {$this->goal}
            Completed: {$completedStr}
            Last result: {$lastResult}

            Based on progress, are additional tasks needed? If yes, generate 1-2 new tasks.
            Format as JSON array or empty array [] if none needed.
            PROMPT;

        try {
            $response = $this->client->messages()->create([
                'model' => 'claude-sonnet-4-5',
                'max_tokens' => 512,
                'system' => 'Generate additional tasks if needed.',
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

            $json = $this->extractTextContent($response->content ?? []);
            $newTasks = json_decode($json, true);

            if (is_array($newTasks) && ! empty($newTasks)) {
                $this->taskQueue = array_merge($this->taskQueue, $newTasks);
            }
        } catch (\Throwable $e) {
            $this->logger->warning("Failed to generate additional tasks: {$e->getMessage()}");
        }
    }

    private function prioritizeTasks(): void
    {
        usort($this->taskQueue, function ($a, $b) {
            return ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0);
        });
    }

    private function formatResults(array $results): string
    {
        $output = "Task Prioritization Results\n";
        $output .= "===========================\n\n";
        $output .= "Goal: {$this->goal}\n\n";
        $output .= 'Completed Tasks: ' . count($this->completedTasks) . "\n";

        foreach ($results as $i => $result) {
            $output .= "\n" . ($i + 1) . '. ' . substr($result, 0, 200) . "...\n";
        }

        return $output;
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

    public function getName(): string
    {
        return $this->name;
    }
}
