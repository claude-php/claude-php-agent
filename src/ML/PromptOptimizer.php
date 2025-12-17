<?php

declare(strict_types=1);

namespace ClaudeAgents\ML;

use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * PromptOptimizer - ML-powered prompt optimization utility.
 *
 * This utility learns from historical prompt performance to suggest
 * optimized prompts for better results. It uses k-NN similarity to find
 * successful prompts for similar tasks and generates improvements.
 *
 * **Features:**
 * - Learn from historical prompt performance
 * - Suggest optimizations based on similar successful prompts
 * - A/B test different prompt variations
 * - Track prompt metrics (response quality, token usage, success rate)
 *
 * @package ClaudeAgents\ML
 */
class PromptOptimizer
{
    private TaskEmbedder $embedder;
    private TaskHistoryStore $historyStore;
    private ClaudePhp $client;
    private LoggerInterface $logger;
    private KNNMatcher $knnMatcher;

    /**
     * @param ClaudePhp $client Claude API client for generating optimizations
     * @param array<string, mixed> $options Configuration:
     *   - history_store_path: Path to prompt history storage (default: storage/prompt_history.json)
     *   - logger: PSR-3 logger
     */
    public function __construct(ClaudePhp $client, array $options = [])
    {
        $this->client = $client;
        $this->logger = $options['logger'] ?? new NullLogger();
        
        $historyPath = $options['history_store_path'] ?? __DIR__ . '/../../storage/prompt_history.json';
        
        $this->embedder = new TaskEmbedder();
        $this->historyStore = new TaskHistoryStore($historyPath, false, 1000);
        $this->knnMatcher = new KNNMatcher();
    }

    /**
     * Optimize a prompt based on historical performance.
     *
     * @param string $originalPrompt The prompt to optimize
     * @param string $taskContext Context about the task/goal
     * @param array<string, mixed> $options Options:
     *   - temperature: Optimization creativity (default: 0.7)
     *   - k: Number of similar prompts to consider (default: 5)
     * @return array{optimized_prompt: string, confidence: float, improvements: array, similar_prompts: array}
     */
    public function optimize(string $originalPrompt, string $taskContext = '', array $options = []): array
    {
        $this->logger->info('Optimizing prompt', [
            'original_length' => strlen($originalPrompt),
            'context' => substr($taskContext, 0, 50),
        ]);

        $k = $options['k'] ?? 5;
        $temperature = $options['temperature'] ?? 0.7;

        // Embed the task context
        $taskAnalysis = [
            'description' => $taskContext ?: $originalPrompt,
            'characteristics' => ['type' => 'prompt_optimization'],
        ];
        $taskEmbedding = $this->embedder->embed($taskAnalysis);

        // Find similar successful prompts
        $similarPrompts = $this->historyStore->findSimilar($taskEmbedding, $k);

        if (empty($similarPrompts)) {
            $this->logger->info('No historical data found, returning original prompt');
            return [
                'optimized_prompt' => $originalPrompt,
                'confidence' => 0.0,
                'improvements' => ['No historical data available'],
                'similar_prompts' => [],
            ];
        }

        // Analyze successful patterns
        $bestPrompts = $this->extractBestPrompts($similarPrompts);
        
        // Generate optimization suggestions
        $optimization = $this->generateOptimization(
            $originalPrompt,
            $bestPrompts,
            $taskContext,
            $temperature
        );

        $this->logger->info('Prompt optimized', [
            'confidence' => $optimization['confidence'],
            'improvements_count' => count($optimization['improvements']),
        ]);

        return $optimization;
    }

    /**
     * Record prompt performance for learning.
     *
     * @param string $prompt The prompt used
     * @param string $taskContext Task context
     * @param float $qualityScore Response quality (0-10)
     * @param int $tokenUsage Total tokens used
     * @param bool $success Whether the prompt succeeded
     * @param float $duration Execution time in seconds
     */
    public function recordPerformance(
        string $prompt,
        string $taskContext,
        float $qualityScore,
        int $tokenUsage,
        bool $success,
        float $duration
    ): void {
        try {
            $taskAnalysis = [
                'description' => $taskContext,
                'characteristics' => ['type' => 'prompt_optimization'],
            ];
            $taskEmbedding = $this->embedder->embed($taskAnalysis);
            
            $this->historyStore->record([
                'id' => uniqid('prompt_', true),
                'task_vector' => $taskEmbedding,
                'agent_id' => 'prompt:' . substr(md5($prompt), 0, 8),
                'quality' => $qualityScore,
                'success' => $success,
                'duration' => $duration,
                'metadata' => [
                    'task' => $taskContext,
                    'prompt' => $prompt,
                    'prompt_length' => strlen($prompt),
                    'token_usage' => $tokenUsage,
                    'tokens_per_second' => $duration > 0 ? $tokenUsage / $duration : 0,
                ],
            ]);

            $this->logger->debug('Recorded prompt performance', [
                'quality_score' => $qualityScore,
                'success' => $success,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error("Failed to record prompt performance: {$e->getMessage()}");
        }
    }

    /**
     * Compare multiple prompt variations (A/B testing).
     *
     * @param array<string> $prompts List of prompt variations
     * @param string $taskContext Task context
     * @return array{winner: string, winner_index: int, comparison: array, confidence: float}
     */
    public function comparePrompts(array $prompts, string $taskContext): array
    {
        if (count($prompts) < 2) {
            throw new \InvalidArgumentException('At least 2 prompts required for comparison');
        }

        $this->logger->info('Comparing prompts', ['count' => count($prompts)]);

        $taskAnalysis = [
            'description' => $taskContext,
            'characteristics' => ['type' => 'prompt_optimization'],
        ];
        $taskEmbedding = $this->embedder->embed($taskAnalysis);
        $similarTasks = $this->historyStore->findSimilar($taskEmbedding, 10);

        $scores = [];
        foreach ($prompts as $index => $prompt) {
            $score = $this->scorePrompt($prompt, $similarTasks);
            $scores[$index] = $score;
        }

        arsort($scores);
        $winnerIndex = array_key_first($scores);
        $winnerScore = $scores[$winnerIndex];
        $secondBestScore = count($scores) > 1 ? $scores[array_keys($scores)[1]] : 0.0;

        $confidence = $winnerScore - $secondBestScore;

        return [
            'winner' => $prompts[$winnerIndex],
            'winner_index' => $winnerIndex,
            'comparison' => $scores,
            'confidence' => round($confidence, 3),
        ];
    }

    /**
     * Get prompt performance statistics.
     *
     * @return array{total_prompts: int, avg_quality: float, avg_tokens: float, success_rate: float}
     */
    public function getStatistics(): array
    {
        return $this->historyStore->getHistoryStats();
    }

    /**
     * Extract best performing prompts from similar tasks.
     */
    private function extractBestPrompts(array $similarTasks): array
    {
        $bestPrompts = [];
        
        foreach ($similarTasks as $task) {
            if ($task['quality_score'] >= 7.0 && $task['is_success']) {
                $metadata = $task['metadata'] ?? [];
                if (isset($metadata['prompt'])) {
                    $bestPrompts[] = [
                        'prompt' => $metadata['prompt'],
                        'quality' => $task['quality_score'],
                        'similarity' => $task['similarity'],
                        'token_usage' => $metadata['token_usage'] ?? 0,
                    ];
                }
            }
        }

        // Sort by quality * similarity
        usort($bestPrompts, function ($a, $b) {
            $scoreA = $a['quality'] * $a['similarity'];
            $scoreB = $b['quality'] * $b['similarity'];
            return $scoreB <=> $scoreA;
        });

        return array_slice($bestPrompts, 0, 3); // Top 3
    }

    /**
     * Generate optimization using Claude based on successful patterns.
     */
    private function generateOptimization(
        string $originalPrompt,
        array $bestPrompts,
        string $taskContext,
        float $temperature
    ): array {
        if (empty($bestPrompts)) {
            return [
                'optimized_prompt' => $originalPrompt,
                'confidence' => 0.0,
                'improvements' => ['No successful examples found'],
                'similar_prompts' => [],
            ];
        }

        $examplesText = '';
        foreach ($bestPrompts as $i => $example) {
            $num = $i + 1;
            $examplesText .= "Example {$num} (Quality: {$example['quality']}, Similarity: {$example['similarity']}):\n";
            $examplesText .= $example['prompt'] . "\n\n";
        }

        $optimizationPrompt = <<<PROMPT
You are a prompt optimization expert. Analyze the following prompt and suggest improvements based on successful patterns.

Original Prompt:
{$originalPrompt}

Task Context:
{$taskContext}

Similar Successful Prompts:
{$examplesText}

Provide an optimized version of the original prompt that incorporates best practices from the successful examples.
Also list 3-5 specific improvements you made.

Format your response as:
OPTIMIZED PROMPT:
[Your optimized prompt here]

IMPROVEMENTS:
1. [First improvement]
2. [Second improvement]
3. [Third improvement]
PROMPT;

        try {
            $response = $this->client->messages()->create([
                'model' => 'claude-sonnet-4-5',
                'max_tokens' => 2048,
                'temperature' => $temperature,
                'messages' => [
                    ['role' => 'user', 'content' => $optimizationPrompt],
                ],
            ]);

            $content = $this->extractContent($response);
            
            // Parse response
            $optimizedPrompt = $this->extractSection($content, 'OPTIMIZED PROMPT:', 'IMPROVEMENTS:');
            $improvementsText = $this->extractSection($content, 'IMPROVEMENTS:', null);
            $improvements = $this->parseImprovements($improvementsText);

            // Calculate confidence based on similarity of best prompts
            $avgSimilarity = array_sum(array_column($bestPrompts, 'similarity')) / count($bestPrompts);
            $avgQuality = array_sum(array_column($bestPrompts, 'quality')) / count($bestPrompts);
            $confidence = ($avgSimilarity * 0.6 + ($avgQuality / 10.0) * 0.4);

            return [
                'optimized_prompt' => trim($optimizedPrompt) ?: $originalPrompt,
                'confidence' => round($confidence, 3),
                'improvements' => $improvements,
                'similar_prompts' => $bestPrompts,
            ];
        } catch (\Throwable $e) {
            $this->logger->error("Failed to generate optimization: {$e->getMessage()}");
            
            return [
                'optimized_prompt' => $originalPrompt,
                'confidence' => 0.0,
                'improvements' => ["Error: {$e->getMessage()}"],
                'similar_prompts' => $bestPrompts,
            ];
        }
    }

    /**
     * Score a prompt based on similarity to successful patterns.
     */
    private function scorePrompt(string $prompt, array $similarTasks): float
    {
        $score = 5.0; // Base score

        // Check for successful patterns
        $promptLower = strtolower($prompt);
        
        // Analyze characteristics
        $hasStructure = preg_match('/\b(step|first|then|finally|task|goal|output)\b/i', $prompt);
        $hasContext = strlen($prompt) > 100;
        $hasExamples = preg_match('/\b(example|for instance|such as)\b/i', $prompt);
        $hasClearInstructions = preg_match('/\b(must|should|please|provide|give|list|explain)\b/i', $prompt);

        $score += $hasStructure ? 1.5 : 0;
        $score += $hasContext ? 1.0 : 0;
        $score += $hasExamples ? 1.5 : 0;
        $score += $hasClearInstructions ? 1.0 : 0;

        // Learn from historical data
        $successfulPatterns = 0;
        foreach ($similarTasks as $task) {
            if ($task['is_success'] && $task['quality_score'] >= 7.0) {
                $metadata = $task['metadata'] ?? [];
                if (isset($metadata['prompt'])) {
                    $historicalPrompt = strtolower($metadata['prompt']);
                    // Check for common words/patterns
                    similar_text($promptLower, $historicalPrompt, $percent);
                    if ($percent > 30) {
                        $successfulPatterns++;
                    }
                }
            }
        }

        $score += ($successfulPatterns * 0.5);

        return min(10.0, $score);
    }

    /**
     * Extract content from Claude response.
     */
    private function extractContent(object $response): string
    {
        if (isset($response->content) && is_array($response->content)) {
            foreach ($response->content as $block) {
                if (isset($block->type) && $block->type === 'text' && isset($block->text)) {
                    return $block->text;
                }
            }
        }
        
        return '';
    }

    /**
     * Extract a section from text between markers.
     */
    private function extractSection(string $text, string $startMarker, ?string $endMarker): string
    {
        $startPos = strpos($text, $startMarker);
        if ($startPos === false) {
            return '';
        }

        $startPos += strlen($startMarker);
        
        if ($endMarker === null) {
            return substr($text, $startPos);
        }

        $endPos = strpos($text, $endMarker, $startPos);
        if ($endPos === false) {
            return substr($text, $startPos);
        }

        return substr($text, $startPos, $endPos - $startPos);
    }

    /**
     * Parse numbered list of improvements.
     */
    private function parseImprovements(string $text): array
    {
        $improvements = [];
        $lines = explode("\n", $text);

        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/^\d+\.\s*(.+)$/', $line, $matches)) {
                $improvements[] = trim($matches[1]);
            }
        }

        return $improvements ?: ['No specific improvements identified'];
    }
}

