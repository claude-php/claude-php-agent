<?php

declare(strict_types=1);

namespace ClaudeAgents\Agents;

use ClaudeAgents\AgentResult;
use ClaudeAgents\Contracts\AgentInterface;
use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Reflection Agent (Generate-Reflect-Refine Pattern).
 *
 * Generates output, reflects on quality, and iteratively refines
 * until a quality threshold is met or max refinements reached.
 */
class ReflectionAgent implements AgentInterface
{
    private ClaudePhp $client;
    private string $name;
    private string $model;
    private int $maxTokens;
    private int $maxRefinements;
    private int $qualityThreshold;
    private ?string $criteria;
    private LoggerInterface $logger;

    /**
     * @param ClaudePhp $client The Claude API client
     * @param array<string, mixed> $options Configuration:
     *   - name: Agent name
     *   - model: Model to use
     *   - max_tokens: Max tokens per response
     *   - max_refinements: Maximum refinement iterations (default: 3)
     *   - quality_threshold: Score threshold to stop (default: 8)
     *   - criteria: Custom evaluation criteria
     *   - logger: PSR-3 logger
     */
    public function __construct(ClaudePhp $client, array $options = [])
    {
        $this->client = $client;
        $this->name = $options['name'] ?? 'reflection_agent';
        $this->model = $options['model'] ?? 'claude-sonnet-4-5';
        $this->maxTokens = $options['max_tokens'] ?? 2048;
        $this->maxRefinements = $options['max_refinements'] ?? 3;
        $this->qualityThreshold = $options['quality_threshold'] ?? 8;
        $this->criteria = $options['criteria'] ?? null;
        $this->logger = $options['logger'] ?? new NullLogger();
    }

    public function run(string $task): AgentResult
    {
        $this->logger->info('Starting reflection agent', ['task' => substr($task, 0, 100)]);

        $totalTokens = ['input' => 0, 'output' => 0];
        $iterations = 0;
        $reflections = [];

        try {
            // Step 1: Initial generation
            $this->logger->debug('Step 1: Initial generation');
            $output = $this->generate($task, $totalTokens);
            $iterations++;

            // Step 2: Reflect and refine loop
            for ($i = 0; $i < $this->maxRefinements; $i++) {
                $this->logger->debug('Reflection iteration ' . ($i + 1));

                // Reflect on the output
                $reflection = $this->reflect($task, $output, $totalTokens);
                $iterations++;

                $score = $this->extractScore($reflection);
                $reflections[] = [
                    'iteration' => $i + 1,
                    'score' => $score,
                    'feedback' => substr($reflection, 0, 200),
                ];

                $this->logger->debug("Reflection score: {$score}");

                // Check if quality threshold met
                if ($score >= $this->qualityThreshold) {
                    $this->logger->info('Quality threshold met at iteration ' . ($i + 1));

                    break;
                }

                // Refine based on reflection
                $this->logger->debug('Refining output');
                $output = $this->refine($task, $output, $reflection, $totalTokens);
                $iterations++;
            }

            return AgentResult::success(
                answer: $output,
                messages: [],
                iterations: $iterations,
                metadata: [
                    'token_usage' => [
                        'input' => $totalTokens['input'],
                        'output' => $totalTokens['output'],
                        'total' => $totalTokens['input'] + $totalTokens['output'],
                    ],
                    'reflections' => $reflections,
                    'final_score' => $reflections[count($reflections) - 1]['score'] ?? 0,
                ],
            );

        } catch (\Throwable $e) {
            $this->logger->error("Reflection agent failed: {$e->getMessage()}");

            return AgentResult::failure($e->getMessage());
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Generate initial output for the task.
     *
     * @param array{input: int, output: int} $tokenUsage
     */
    private function generate(string $task, array &$tokenUsage): string
    {
        $response = $this->client->messages()->create([
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'messages' => [['role' => 'user', 'content' => $task]],
        ]);

        $tokenUsage['input'] += $response->usage->input_tokens ?? 0;
        $tokenUsage['output'] += $response->usage->output_tokens ?? 0;

        return $this->extractTextContent($response->content ?? []);
    }

    /**
     * Reflect on the output quality.
     *
     * @param array{input: int, output: int} $tokenUsage
     */
    private function reflect(string $task, string $output, array &$tokenUsage): string
    {
        $criteria = $this->criteria ?? 'correctness, completeness, clarity, and quality';

        $prompt = "Task: {$task}\n\n" .
                  "Current output:\n{$output}\n\n" .
                  "Evaluate this output on {$criteria}:\n" .
                  "1. What's working well?\n" .
                  "2. What issues or problems exist?\n" .
                  "3. How can it be improved?\n" .
                  '4. Overall quality score (1-10)';

        $response = $this->client->messages()->create([
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'system' => 'You are an expert evaluator. Be constructive but critical.',
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        $tokenUsage['input'] += $response->usage->input_tokens ?? 0;
        $tokenUsage['output'] += $response->usage->output_tokens ?? 0;

        return $this->extractTextContent($response->content ?? []);
    }

    /**
     * Refine the output based on reflection.
     *
     * @param array{input: int, output: int} $tokenUsage
     */
    private function refine(string $task, string $output, string $reflection, array &$tokenUsage): string
    {
        $prompt = "Task: {$task}\n\n" .
                  "Current output:\n{$output}\n\n" .
                  "Reflection:\n{$reflection}\n\n" .
                  'Improve the output by addressing the issues identified in the reflection.';

        $response = $this->client->messages()->create([
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        $tokenUsage['input'] += $response->usage->input_tokens ?? 0;
        $tokenUsage['output'] += $response->usage->output_tokens ?? 0;

        return $this->extractTextContent($response->content ?? []);
    }

    /**
     * Extract quality score from reflection text.
     */
    private function extractScore(string $text): int
    {
        // Look for patterns like "Score: 7/10" or "Quality: 8" or "score of 7" or "rating of 6"
        if (preg_match('/(?:score|quality|rating)(?:\s+of\s+|[:\s]+)(\d+)(?:\/10)?/i', $text, $matches)) {
            return min(10, max(1, (int) $matches[1]));
        }

        // Look for standalone numbers near "10" like "7/10"
        if (preg_match('/(\d+)\s*\/\s*10/i', $text, $matches)) {
            return min(10, max(1, (int) $matches[1]));
        }

        return 5; // Default if no score found
    }

    /**
     * Extract text content from response blocks.
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
