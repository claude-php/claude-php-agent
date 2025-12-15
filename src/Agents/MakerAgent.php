<?php

declare(strict_types=1);

namespace ClaudeAgents\Agents;

use ClaudeAgents\AgentResult;
use ClaudeAgents\Contracts\AgentInterface;
use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * MAKER Agent: Massively Decomposed Agentic Processes.
 *
 * Implements the MAKER framework from the paper "Solving a Million-Step LLM Task with Zero Errors"
 * (https://arxiv.org/html/2511.09030v1).
 *
 * Key components:
 * - Maximal Agentic Decomposition: Breaks tasks into minimal subtasks
 * - First-to-ahead-by-K Error correction: Multi-agent voting for each subtask
 * - Red-flagging: Recognizing signs of unreliable responses
 *
 * This agent can solve tasks requiring millions of steps with near-zero error rates
 * by extreme decomposition and error correction at each step.
 */
class MakerAgent implements AgentInterface
{
    private ClaudePhp $client;
    private string $name;
    private string $model;
    private int $maxTokens;
    private LoggerInterface $logger;
    private int $votingK;
    private bool $enableRedFlagging;
    private int $maxDecompositionDepth;

    /**
     * @var array<MicroAgent>
     */
    private array $microAgents = [];

    /**
     * @var array<string, mixed>
     */
    private array $executionStats = [];

    /**
     * @param ClaudePhp $client The Claude API client
     * @param array<string, mixed> $options Configuration:
     *   - name: Agent name
     *   - model: Model to use (default: claude-sonnet-4-5)
     *   - max_tokens: Max tokens per response
     *   - voting_k: K value for first-to-ahead-by-k voting (default: 3)
     *   - enable_red_flagging: Enable red-flagging detection (default: true)
     *   - max_decomposition_depth: Maximum depth for task decomposition (default: 10)
     *   - logger: PSR-3 logger
     */
    public function __construct(ClaudePhp $client, array $options = [])
    {
        $this->client = $client;
        $this->name = $options['name'] ?? 'maker_agent';
        $this->model = $options['model'] ?? 'claude-sonnet-4-5';
        $this->maxTokens = $options['max_tokens'] ?? 2048;
        $this->votingK = $options['voting_k'] ?? 3;
        $this->enableRedFlagging = $options['enable_red_flagging'] ?? true;
        $this->maxDecompositionDepth = $options['max_decomposition_depth'] ?? 10;
        $this->logger = $options['logger'] ?? new NullLogger();

        $this->resetStats();
    }

    public function run(string $task): AgentResult
    {
        $this->logger->info('MAKER Agent starting', [
            'task' => substr($task, 0, 100),
            'voting_k' => $this->votingK,
            'red_flagging' => $this->enableRedFlagging,
        ]);

        $startTime = microtime(true);
        $this->resetStats();

        try {
            // Execute task using MDAP framework
            $result = $this->executeWithMDAP($task, 0);

            $duration = microtime(true) - $startTime;

            $this->logger->info('MAKER Agent completed', [
                'success' => true,
                'duration' => round($duration, 2),
                'stats' => $this->executionStats,
            ]);

            return AgentResult::success(
                answer: $result,
                messages: [],
                iterations: $this->executionStats['total_steps'],
                metadata: [
                    'execution_stats' => $this->executionStats,
                    'duration_seconds' => round($duration, 2),
                    'error_rate' => $this->calculateErrorRate(),
                    'voting_k' => $this->votingK,
                    'red_flagging_enabled' => $this->enableRedFlagging,
                ],
            );
        } catch (\Throwable $e) {
            $this->logger->error("MAKER Agent failed: {$e->getMessage()}");

            return AgentResult::failure(
                error: $e->getMessage(),
                metadata: [
                    'execution_stats' => $this->executionStats,
                ],
            );
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Execute task using Massively Decomposed Agentic Processes.
     */
    private function executeWithMDAP(string $task, int $depth): string
    {
        $this->logger->debug('MDAP execution', ['task' => substr($task, 0, 50), 'depth' => $depth]);

        // Check if we should decompose further
        if ($this->shouldDecompose($task, $depth)) {
            return $this->decomposeAndExecute($task, $depth);
        }

        // Execute as atomic subtask with voting
        return $this->executeAtomicSubtaskWithVoting($task);
    }

    /**
     * Determine if task should be decomposed further.
     */
    private function shouldDecompose(string $task, int $depth): bool
    {
        if ($depth >= $this->maxDecompositionDepth) {
            return false;
        }

        // Heuristics for decomposition (can be extended)
        $taskLength = strlen($task);
        $hasMultipleSteps = preg_match('/\b(then|next|after|finally|also|and)\b/i', $task);
        $hasEnumeration = preg_match('/\b(first|second|third|1\.|2\.|3\.)/i', $task);

        return $taskLength > 100 || $hasMultipleSteps || $hasEnumeration;
    }

    /**
     * Decompose task and execute subtasks recursively.
     */
    private function decomposeAndExecute(string $task, int $depth): string
    {
        $this->logger->debug("Decomposing task at depth {$depth}");

        // Get subtasks through voting
        $subtasks = $this->decomposeWithVoting($task);

        if (empty($subtasks)) {
            // Fallback to atomic execution
            return $this->executeAtomicSubtaskWithVoting($task);
        }

        $this->executionStats['decompositions']++;
        $this->executionStats['subtasks_created'] += count($subtasks);

        // Execute each subtask recursively
        $subtaskResults = [];
        foreach ($subtasks as $i => $subtask) {
            $this->logger->debug("Executing subtask {$i}/{" . count($subtasks) . '}');
            $subtaskResults[] = $this->executeWithMDAP($subtask, $depth + 1);
        }

        // Compose results
        return $this->composeResults($task, $subtaskResults);
    }

    /**
     * Decompose task with voting to ensure correct decomposition.
     */
    private function decomposeWithVoting(string $task): array
    {
        $this->logger->debug('Decomposing with voting');

        $prompt = $this->buildDecompositionPrompt($task);
        $candidates = [];

        // Generate N = 2k-1 decomposition candidates
        $n = 2 * $this->votingK - 1;

        for ($i = 0; $i < $n; $i++) {
            try {
                $microAgent = $this->createMicroAgent('decomposer');
                $response = $microAgent->execute($prompt);

                // Check for red flags
                if ($this->enableRedFlagging && $this->hasRedFlags($response)) {
                    $this->executionStats['red_flags_detected']++;
                    $this->logger->debug('Red flag detected in decomposition, retrying');
                    $i--; // Retry this candidate

                    continue;
                }

                $subtasks = $this->parseDecomposition($response);
                $candidateKey = $this->hashSubtasks($subtasks);

                if (! isset($candidates[$candidateKey])) {
                    $candidates[$candidateKey] = [
                        'subtasks' => $subtasks,
                        'votes' => 0,
                    ];
                }

                $candidates[$candidateKey]['votes']++;

                // Check for first-to-ahead-by-k
                if ($this->hasWinner($candidates)) {
                    break;
                }
            } catch (\Throwable $e) {
                $this->logger->warning("Decomposition candidate failed: {$e->getMessage()}");
            }
        }

        // Return winning candidate or most voted
        $winner = $this->getWinningCandidate($candidates);

        return $winner['subtasks'] ?? [];
    }

    /**
     * Execute atomic subtask with voting for error correction.
     */
    private function executeAtomicSubtaskWithVoting(string $task): string
    {
        $this->logger->debug('Executing atomic subtask with voting');
        $this->executionStats['atomic_executions']++;
        $this->executionStats['total_steps']++;

        $prompt = $this->buildExecutionPrompt($task);
        $candidates = [];
        $attempts = 0;
        $maxAttempts = 2 * $this->votingK - 1 + 10; // Allow extra attempts for pathological cases

        while ($attempts < $maxAttempts) {
            try {
                $microAgent = $this->createMicroAgent('executor');
                $response = $microAgent->execute($prompt);

                // Check for red flags
                if ($this->enableRedFlagging && $this->hasRedFlags($response)) {
                    $this->executionStats['red_flags_detected']++;
                    $this->logger->debug('Red flag detected, retrying');
                    $attempts++;

                    continue;
                }

                $answer = $this->parseExecutionResult($response);
                $candidateKey = $this->hashAnswer($answer);

                if (! isset($candidates[$candidateKey])) {
                    $candidates[$candidateKey] = [
                        'answer' => $answer,
                        'votes' => 0,
                    ];
                }

                $candidates[$candidateKey]['votes']++;
                $this->executionStats['votes_cast']++;

                // Check for first-to-ahead-by-k winner
                if ($this->hasWinner($candidates)) {
                    $winner = $this->getWinningCandidate($candidates);

                    return $winner['answer'];
                }
            } catch (\Throwable $e) {
                $this->logger->warning("Execution candidate failed: {$e->getMessage()}");
            }

            $attempts++;
        }

        // Return best candidate if no clear winner
        $winner = $this->getWinningCandidate($candidates);

        return $winner['answer'] ?? "Unable to reach consensus on: {$task}";
    }

    /**
     * Check if any candidate has won by first-to-ahead-by-k rule.
     */
    private function hasWinner(array $candidates): bool
    {
        if (count($candidates) < 2) {
            return false;
        }

        $votes = array_column($candidates, 'votes');
        rsort($votes);

        $topVotes = $votes[0] ?? 0;
        $secondVotes = $votes[1] ?? 0;

        return ($topVotes - $secondVotes) >= $this->votingK;
    }

    /**
     * Get the winning candidate (most votes).
     */
    private function getWinningCandidate(array $candidates): array
    {
        if (empty($candidates)) {
            return [];
        }

        uasort($candidates, function ($a, $b) {
            return $b['votes'] <=> $a['votes'];
        });

        return reset($candidates);
    }

    /**
     * Detect red flags indicating unreliable response.
     */
    private function hasRedFlags(string $response): bool
    {
        if (! $this->enableRedFlagging) {
            return false;
        }

        // Red flag indicators from the paper
        $redFlags = [
            'wait, maybe' => 1,
            'not as we think' => 1,
            'let me reconsider' => 1,
            'actually' => 0.5,
            'on second thought' => 1,
            'wait' => 0.3,
            'hmm' => 0.3,
        ];

        $lowerResponse = strtolower($response);
        $redFlagScore = 0;

        foreach ($redFlags as $flag => $weight) {
            if (str_contains($lowerResponse, $flag)) {
                $redFlagScore += $weight;
            }
        }

        // Also check for circular reasoning (repeating same phrases)
        $sentences = preg_split('/[.!?]+/', $response);
        $uniqueSentences = count(array_unique($sentences));
        $totalSentences = count($sentences);

        if ($totalSentences > 3 && ($uniqueSentences / $totalSentences) < 0.7) {
            $redFlagScore += 0.5;
        }

        return $redFlagScore >= 1.0;
    }

    /**
     * Create a micro-agent for a specific subtask.
     */
    private function createMicroAgent(string $role): MicroAgent
    {
        return new MicroAgent($this->client, [
            'role' => $role,
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'logger' => $this->logger,
        ]);
    }

    /**
     * Build decomposition prompt.
     */
    private function buildDecompositionPrompt(string $task): string
    {
        return <<<PROMPT
            You are a task decomposer. Break down the following task into minimal, independent subtasks.

            Task: {$task}

            Decompose this into clear, sequential subtasks. Each subtask should be:
            - Atomic (cannot be easily decomposed further)
            - Clear and unambiguous
            - Independent where possible

            Format your response as a numbered list:
            1. [First subtask]
            2. [Second subtask]
            3. [Third subtask]
            ...

            If the task is already atomic, respond with: "ATOMIC_TASK: [original task]"
            PROMPT;
    }

    /**
     * Build execution prompt.
     */
    private function buildExecutionPrompt(string $task): string
    {
        return <<<PROMPT
            You are a specialized executor. Execute the following atomic task precisely and concisely.

            Task: {$task}

            Provide your answer directly without unnecessary elaboration.
            If you are unsure, state your uncertainty clearly.
            PROMPT;
    }

    /**
     * Parse decomposition response.
     */
    private function parseDecomposition(string $response): array
    {
        // Check if task is atomic
        if (preg_match('/ATOMIC_TASK:\s*(.+)/i', $response, $matches)) {
            return [trim($matches[1])];
        }

        // Parse numbered list
        $subtasks = [];
        $lines = explode("\n", $response);

        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/^\d+\.\s*(.+)$/', $line, $matches)) {
                $subtasks[] = trim($matches[1]);
            }
        }

        return $subtasks;
    }

    /**
     * Parse execution result.
     */
    private function parseExecutionResult(string $response): string
    {
        return trim($response);
    }

    /**
     * Compose subtask results into final answer.
     */
    private function composeResults(string $originalTask, array $subtaskResults): string
    {
        if (count($subtaskResults) === 1) {
            return $subtaskResults[0];
        }

        $composedResults = implode("\n", $subtaskResults);

        // Use voting for composition as well
        return $this->composeWithVoting($originalTask, $composedResults);
    }

    /**
     * Compose results with voting.
     */
    private function composeWithVoting(string $originalTask, string $subtaskResults): string
    {
        $prompt = <<<PROMPT
            You are a result composer. Combine the following subtask results into a coherent answer.

            Original Task: {$originalTask}

            Subtask Results:
            {$subtaskResults}

            Provide a clear, concise synthesis of these results.
            PROMPT;

        $candidates = [];
        $n = 2 * $this->votingK - 1;

        for ($i = 0; $i < $n; $i++) {
            try {
                $microAgent = $this->createMicroAgent('composer');
                $response = $microAgent->execute($prompt);

                if ($this->enableRedFlagging && $this->hasRedFlags($response)) {
                    $this->executionStats['red_flags_detected']++;

                    continue;
                }

                $answer = trim($response);
                $candidateKey = $this->hashAnswer($answer);

                if (! isset($candidates[$candidateKey])) {
                    $candidates[$candidateKey] = [
                        'answer' => $answer,
                        'votes' => 0,
                    ];
                }

                $candidates[$candidateKey]['votes']++;

                if ($this->hasWinner($candidates)) {
                    break;
                }
            } catch (\Throwable $e) {
                $this->logger->warning("Composition candidate failed: {$e->getMessage()}");
            }
        }

        $winner = $this->getWinningCandidate($candidates);

        return $winner['answer'] ?? $subtaskResults;
    }

    /**
     * Hash subtasks for comparison.
     */
    private function hashSubtasks(array $subtasks): string
    {
        return md5(implode('|', $subtasks));
    }

    /**
     * Hash answer for comparison.
     */
    private function hashAnswer(string $answer): string
    {
        // Normalize whitespace and case for comparison
        $normalized = strtolower(preg_replace('/\s+/', ' ', trim($answer)));

        return md5($normalized);
    }

    /**
     * Calculate error rate from execution stats.
     */
    private function calculateErrorRate(): float
    {
        if ($this->executionStats['total_steps'] === 0) {
            return 0.0;
        }

        // Estimate error rate based on voting patterns
        $avgVotesPerStep = $this->executionStats['votes_cast'] / max(1, $this->executionStats['atomic_executions']);

        // If we need more votes, error rate is higher
        $estimatedErrorRate = 1 - (1 / $avgVotesPerStep);

        return round($estimatedErrorRate, 4);
    }

    /**
     * Reset execution statistics.
     */
    private function resetStats(): void
    {
        $this->executionStats = [
            'total_steps' => 0,
            'atomic_executions' => 0,
            'decompositions' => 0,
            'subtasks_created' => 0,
            'votes_cast' => 0,
            'red_flags_detected' => 0,
        ];
    }

    /**
     * Get execution statistics.
     */
    public function getExecutionStats(): array
    {
        return $this->executionStats;
    }

    /**
     * Set voting K parameter.
     */
    public function setVotingK(int $k): self
    {
        $this->votingK = $k;

        return $this;
    }

    /**
     * Enable or disable red-flagging.
     */
    public function setRedFlagging(bool $enabled): self
    {
        $this->enableRedFlagging = $enabled;

        return $this;
    }
}
