<?php

declare(strict_types=1);

namespace ClaudeAgents\Agents;

use ClaudeAgents\AgentResult;
use ClaudeAgents\Support\TextContentExtractor;
use ClaudePhp\ClaudePhp;

/**
 * Hierarchical Agent (Master-Worker Pattern).
 *
 * Coordinates multiple specialized worker agents to solve complex
 * multi-domain tasks. The master decomposes tasks, delegates to
 * workers, and synthesizes results.
 */
class HierarchicalAgent extends AbstractAgent
{
    /**
     * @var array<string, WorkerAgent>
     */
    private array $workers = [];

    protected const DEFAULT_NAME = 'master_agent';

    /**
     * @param ClaudePhp $client The Claude API client
     * @param array<string, mixed> $options Configuration:
     *   - name: Master agent name
     *   - model: Model to use
     *   - max_tokens: Max tokens per response
     *   - logger: PSR-3 logger
     */
    public function __construct(ClaudePhp $client, array $options = [])
    {
        parent::__construct($client, $options);
    }

    /**
     * Register a worker agent.
     */
    public function registerWorker(string $name, WorkerAgent $worker): self
    {
        $this->workers[$name] = $worker;
        $this->logger->debug("Registered worker: {$name}");

        return $this;
    }

    /**
     * Get a registered worker.
     */
    public function getWorker(string $name): ?WorkerAgent
    {
        return $this->workers[$name] ?? null;
    }

    /**
     * Get all worker names.
     *
     * @return array<string>
     */
    public function getWorkerNames(): array
    {
        return array_keys($this->workers);
    }

    public function run(string $task): AgentResult
    {
        $this->logStart($task);

        $startTime = microtime(true);
        $totalTokens = ['input' => 0, 'output' => 0];
        $workerResults = [];

        try {
            // Step 1: Decompose the task
            $this->logDebug('Step 1: Decomposing task');
            $subtasks = $this->decompose($task, $totalTokens);

            if (empty($subtasks)) {
                return AgentResult::failure('Failed to decompose task into subtasks');
            }

            // Step 2: Execute subtasks with workers
            $this->logDebug('Step 2: Executing subtasks', ['count' => count($subtasks)]);

            foreach ($subtasks as $i => $subtask) {
                $workerName = $subtask['agent'] ?? 'default';
                $subtaskText = $subtask['task'] ?? '';

                $this->logDebug("Executing subtask {$i}", [
                    'worker' => $workerName,
                    'task' => substr($subtaskText, 0, 50),
                ]);

                $worker = $this->workers[$workerName] ?? null;

                if ($worker === null) {
                    // Try to find any available worker
                    $worker = reset($this->workers) ?: null;
                    if ($worker !== null) {
                        $workerName = array_key_first($this->workers);
                    }
                }

                if ($worker !== null) {
                    $result = $worker->run($subtaskText);
                    $workerResults[$workerName] = $result->getAnswer();

                    $usage = $result->getTokenUsage();
                    $totalTokens['input'] += $usage['input'];
                    $totalTokens['output'] += $usage['output'];
                } else {
                    $workerResults[$workerName] = "No worker available for: {$subtaskText}";
                }
            }

            // Step 3: Synthesize results
            $this->logDebug('Step 3: Synthesizing results');
            $finalAnswer = $this->synthesize($task, $workerResults, $totalTokens);

            $duration = microtime(true) - $startTime;

            return AgentResult::success(
                answer: $finalAnswer,
                messages: [],
                iterations: count($subtasks) + 2, // decompose + subtasks + synthesize
                metadata: [
                    'token_usage' => [
                        'input' => $totalTokens['input'],
                        'output' => $totalTokens['output'],
                        'total' => $totalTokens['input'] + $totalTokens['output'],
                    ],
                    'workers_used' => array_keys($workerResults),
                    'subtasks' => count($subtasks),
                    'duration_seconds' => round($duration, 2),
                ],
            );
        } catch (\Throwable $e) {
            $this->logError($e->getMessage());

            return AgentResult::failure($e->getMessage());
        }
    }

    /**
     * Decompose a complex task into subtasks.
     *
     * @param string $task The task to decompose
     * @param array{input: int, output: int} $tokenUsage Token usage tracker
     * @return array<array{agent: string, task: string}>
     */
    private function decompose(string $task, array &$tokenUsage): array
    {
        // Build worker descriptions
        $workersList = '';
        foreach ($this->workers as $name => $worker) {
            $workersList .= "- {$name}: {$worker->getSpecialty()}\n";
        }

        $prompt = "Complex task: {$task}\n\n" .
            "Available specialized agents:\n{$workersList}\n" .
            "Decompose this task into subtasks. For each subtask:\n" .
            "1. Specify which agent should handle it\n" .
            "2. Describe the subtask clearly\n\n" .
            "Format each subtask as:\n" .
            "Agent: [agent_name]\n" .
            'Subtask: [description]';

        try {
            $response = $this->client->messages()->create([
                'model' => $this->model,
                'max_tokens' => $this->maxTokens,
                'system' => 'You are a master coordinator. Delegate tasks efficiently to specialized agents.',
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

            $tokenUsage['input'] += $response->usage->input_tokens ?? 0;
            $tokenUsage['output'] += $response->usage->output_tokens ?? 0;

            $text = TextContentExtractor::extractFromResponse($response);

            return $this->parseSubtasks($text);
        } catch (\Throwable $e) {
            $this->logError("Decomposition failed: {$e->getMessage()}");

            return [['agent' => 'default', 'task' => $task]];
        }
    }

    /**
     * Parse subtasks from decomposition text.
     *
     * @return array<array{agent: string, task: string}>
     */
    private function parseSubtasks(string $text): array
    {
        $lines = explode("\n", $text);
        $subtasks = [];
        $current = null;

        foreach ($lines as $line) {
            $line = trim($line);

            if (preg_match('/^Agent:\s*(.+)$/i', $line, $matches)) {
                if ($current !== null && isset($current['task'])) {
                    $subtasks[] = $current;
                }
                $current = ['agent' => trim($matches[1]), 'task' => ''];
            } elseif (preg_match('/^Subtask:\s*(.+)$/i', $line, $matches)) {
                if ($current !== null) {
                    $current['task'] = trim($matches[1]);
                }
            }
        }

        if ($current !== null && isset($current['task']) && $current['task'] !== '') {
            $subtasks[] = $current;
        }

        return $subtasks;
    }

    /**
     * Synthesize worker results into a final answer.
     *
     * @param string $task Original task
     * @param array<string, string> $results Worker results
     * @param array{input: int, output: int} $tokenUsage Token usage tracker
     */
    private function synthesize(string $task, array $results, array &$tokenUsage): string
    {
        if (empty($results)) {
            return 'No results to synthesize';
        }

        $resultsText = '';
        foreach ($results as $agent => $output) {
            $resultsText .= "=== {$agent} Output ===\n{$output}\n\n";
        }

        $prompt = "Original task: {$task}\n\n" .
            "Worker outputs:\n{$resultsText}\n" .
            'Synthesize these into a comprehensive, coherent final answer.';

        try {
            $response = $this->client->messages()->create([
                'model' => $this->model,
                'max_tokens' => $this->maxTokens,
                'system' => 'You synthesize outputs from multiple agents into clear, unified responses.',
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

            $tokenUsage['input'] += $response->usage->input_tokens ?? 0;
            $tokenUsage['output'] += $response->usage->output_tokens ?? 0;

            return TextContentExtractor::extractFromResponse($response);
        } catch (\Throwable $e) {
            $this->logError("Synthesis failed: {$e->getMessage()}");

            return 'Synthesis error: ' . $e->getMessage();
        }
    }
}
