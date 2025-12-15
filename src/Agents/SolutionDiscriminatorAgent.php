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
 * Solution Discriminator Agent - Evaluates and votes on solution quality.
 * Extends MAKER voting mechanism for general solution comparison.
 */
class SolutionDiscriminatorAgent implements AgentInterface
{
    private ClaudePhp $client;
    private string $name;
    private array $criteria = [];
    private LoggerInterface $logger;

    public function __construct(ClaudePhp $client, array $options = [])
    {
        $this->client = $client;
        $this->name = $options['name'] ?? 'solution_discriminator';
        $this->criteria = $options['criteria'] ?? ['correctness', 'completeness', 'quality'];
        $this->logger = $options['logger'] ?? new NullLogger();
    }

    public function run(string $task): AgentResult
    {
        // Expecting task to contain solutions to evaluate
        $this->logger->info('Evaluating solutions');

        try {
            $solutions = $this->parseSolutions($task);
            $evaluations = $this->evaluateSolutions($solutions);
            $best = $this->selectBest($evaluations);

            return AgentResult::success(
                answer: "Best solution: {$best['solution_id']} (score: {$best['total_score']})",
                messages: [],
                iterations: count($solutions),
                metadata: [
                    'evaluations' => $evaluations,
                    'best_solution' => $best,
                ],
            );
        } catch (\Throwable $e) {
            return AgentResult::failure(error: $e->getMessage());
        }
    }

    public function evaluateSolutions(array $solutions, ?string $context = null): array
    {
        $evaluations = [];

        foreach ($solutions as $solution) {
            $scores = [];

            foreach ($this->criteria as $criterion) {
                $scores[$criterion] = $this->evaluateCriterion($solution, $criterion, $context);
            }

            $totalScore = array_sum($scores) / count($scores);

            $evaluations[] = [
                'solution_id' => $solution['id'] ?? uniqid('sol_'),
                'solution' => $solution,
                'scores' => $scores,
                'total_score' => $totalScore,
            ];
        }

        return $evaluations;
    }

    private function evaluateCriterion(array $solution, string $criterion, ?string $context): float
    {
        $solutionStr = is_string($solution) ? $solution : json_encode($solution);
        $contextStr = $context ? "Context: {$context}\n\n" : '';

        $prompt = <<<PROMPT
            {$contextStr}Solution: {$solutionStr}

            Evaluate this solution on the criterion: {$criterion}

            Rate from 0.0 (worst) to 1.0 (best). Respond with just the number.
            PROMPT;

        try {
            $response = $this->client->messages()->create([
                'model' => 'claude-sonnet-4-5',
                'max_tokens' => 128,
                'system' => 'You are a solution evaluator. Rate solutions objectively.',
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

            $rating = trim(TextContentExtractor::extractFromResponse($response));

            return (float)$rating;
        } catch (\Throwable $e) {
            $this->logger->warning("Evaluation failed for {$criterion}: {$e->getMessage()}");

            return 0.5;
        }
    }

    private function selectBest(array $evaluations): array
    {
        if (empty($evaluations)) {
            return ['solution_id' => 'none', 'total_score' => 0.0];
        }

        usort($evaluations, fn ($a, $b) => $b['total_score'] <=> $a['total_score']);

        return $evaluations[0];
    }

    private function parseSolutions(string $task): array
    {
        // Simple parsing - expects JSON array of solutions
        $decoded = json_decode($task, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        // Fallback: treat as single solution
        return [['id' => 'solution_1', 'content' => $task]];
    }

    public function getName(): string
    {
        return $this->name;
    }
}
