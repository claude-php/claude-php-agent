<?php

declare(strict_types=1);

namespace ClaudeAgents\Agents;

use ClaudeAgents\AgentResult;
use ClaudeAgents\Contracts\AgentInterface;
use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Adaptive Agent Service - Intelligently selects the best agent for a task,
 * validates results, and adapts if needed.
 *
 * This service acts as a meta-agent that:
 * 1. Analyzes the input task to understand requirements
 * 2. Selects the most appropriate agent from available options
 * 3. Executes the task with the selected agent
 * 4. Validates the result quality and correctness
 * 5. Adapts by trying different agents or reframing the request if needed
 *
 * Features:
 * - Intelligent agent selection based on task characteristics
 * - Result validation with quality scoring
 * - Automatic retry with different agents on failure
 * - Request reframing for better results
 * - Learning from successes and failures
 * - Performance tracking per agent
 *
 * @package ClaudeAgents\Agents
 */
class AdaptiveAgentService implements AgentInterface
{
    private ClaudePhp $client;
    private string $name;
    private array $agents = [];
    private array $agentProfiles = [];
    private array $performance = [];
    private int $maxAttempts;
    private float $qualityThreshold;
    private bool $enableReframing;
    private LoggerInterface $logger;

    /**
     * Create a new Adaptive Agent Service.
     *
     * @param ClaudePhp $client Claude PHP client instance
     * @param array $options Configuration options:
     *   - name: Service name (default: 'adaptive_agent_service')
     *   - max_attempts: Maximum number of attempts to get a good result (default: 3)
     *   - quality_threshold: Minimum quality score (0-10) to accept result (default: 7.0)
     *   - enable_reframing: Whether to reframe requests on failure (default: true)
     *   - logger: PSR-3 logger instance
     */
    public function __construct(ClaudePhp $client, array $options = [])
    {
        $this->client = $client;
        $this->name = $options['name'] ?? 'adaptive_agent_service';
        $this->maxAttempts = $options['max_attempts'] ?? 3;
        $this->qualityThreshold = $options['quality_threshold'] ?? 7.0;
        $this->enableReframing = $options['enable_reframing'] ?? true;
        $this->logger = $options['logger'] ?? new NullLogger();
    }

    /**
     * Register an agent with the service.
     *
     * @param string $id Unique agent identifier
     * @param AgentInterface $agent The agent instance
     * @param array $profile Agent profile describing its strengths:
     *   - type: Agent type (e.g., 'react', 'reflection', 'rag', etc.)
     *   - strengths: Array of strength descriptions
     *   - best_for: Array of use case descriptions
     *   - complexity_level: 'simple', 'medium', 'complex', 'extreme'
     *   - speed: 'fast', 'medium', 'slow'
     *   - quality: 'standard', 'high', 'extreme'
     */
    public function registerAgent(string $id, AgentInterface $agent, array $profile = []): void
    {
        $this->agents[$id] = $agent;
        $this->agentProfiles[$id] = array_merge([
            'type' => 'unknown',
            'strengths' => [],
            'best_for' => [],
            'complexity_level' => 'medium',
            'speed' => 'medium',
            'quality' => 'standard',
        ], $profile);

        $this->performance[$id] = [
            'attempts' => 0,
            'successes' => 0,
            'failures' => 0,
            'average_quality' => 0.0,
            'total_duration' => 0.0,
        ];

        $this->logger->info("Registered agent: {$id}", ['profile' => $this->agentProfiles[$id]]);
    }

    /**
     * Run the adaptive agent service.
     *
     * This will analyze the task, select the best agent, validate the result,
     * and adapt if necessary to get a high-quality answer.
     *
     * @param string $task The task to execute
     * @return AgentResult The final result after adaptation
     */
    public function run(string $task): AgentResult
    {
        $this->logger->info("Adaptive Agent Service: {$task}");
        $startTime = microtime(true);
        $attempts = [];

        // Analyze the task to understand requirements
        $taskAnalysis = $this->analyzeTask($task);
        $this->logger->debug('Task analysis', $taskAnalysis);

        // Try up to maxAttempts to get a good result
        for ($attempt = 1; $attempt <= $this->maxAttempts; $attempt++) {
            $this->logger->info("Attempt {$attempt}/{$this->maxAttempts}");

            // Select the best agent for this attempt
            $selectedAgentId = $this->selectBestAgent($taskAnalysis, $attempts);

            if (! $selectedAgentId) {
                $this->logger->error('No suitable agent found');

                return AgentResult::failure(
                    error: 'No suitable agent available for this task',
                    metadata: [
                        'task_analysis' => $taskAnalysis,
                        'attempts' => $attempts,
                    ]
                );
            }

            $this->logger->info("Selected agent: {$selectedAgentId}");

            // Execute with the selected agent
            $attemptStart = microtime(true);
            $result = $this->executeWithAgent($selectedAgentId, $task);
            $attemptDuration = microtime(true) - $attemptStart;

            // Validate the result
            $validation = $this->validateResult($task, $result, $taskAnalysis);
            $this->logger->info("Validation score: {$validation['quality_score']}/10");

            // Track this attempt
            $attempts[] = [
                'attempt' => $attempt,
                'agent_id' => $selectedAgentId,
                'agent_type' => $this->agentProfiles[$selectedAgentId]['type'],
                'duration' => round($attemptDuration, 3),
                'success' => $result->isSuccess(),
                'validation' => $validation,
            ];

            // Update performance metrics
            $this->updatePerformance($selectedAgentId, $validation['quality_score'], $attemptDuration);

            // Check if result is good enough
            if ($result->isSuccess() && $validation['quality_score'] >= $this->qualityThreshold) {
                $this->logger->info('Success! Quality threshold met.');
                $totalDuration = microtime(true) - $startTime;

                return AgentResult::success(
                    answer: $result->getAnswer(),
                    messages: $result->getMessages(),
                    iterations: $attempt,
                    metadata: array_merge($result->getMetadata(), [
                        'service_name' => $this->name,
                        'task_analysis' => $taskAnalysis,
                        'attempts' => $attempts,
                        'final_agent' => $selectedAgentId,
                        'final_quality' => $validation['quality_score'],
                        'total_duration' => round($totalDuration, 3),
                    ])
                );
            }

            // If not good enough and we have more attempts, try to improve
            if ($attempt < $this->maxAttempts) {
                $this->logger->info('Result quality insufficient, attempting to improve...');

                // Should we reframe the task?
                if ($this->enableReframing && $validation['quality_score'] < $this->qualityThreshold - 2) {
                    $this->logger->info('Quality significantly below threshold, reframing task...');
                    $reframedTask = $this->reframeTask($task, $validation['issues']);
                    $this->logger->debug("Reframed task: {$reframedTask}");
                    $task = $reframedTask;
                }
            }
        }

        // All attempts exhausted
        $this->logger->warning('Max attempts reached without meeting quality threshold');
        $totalDuration = microtime(true) - $startTime;

        // Return the best result we got
        $bestAttempt = $this->getBestAttempt($attempts);

        return AgentResult::failure(
            error: "Could not achieve quality threshold after {$this->maxAttempts} attempts. Best score: {$bestAttempt['validation']['quality_score']}/10",
            metadata: [
                'service_name' => $this->name,
                'task_analysis' => $taskAnalysis,
                'attempts' => $attempts,
                'best_attempt' => $bestAttempt,
                'total_duration' => round($totalDuration, 3),
            ]
        );
    }

    /**
     * Analyze the task to understand its requirements and characteristics.
     *
     * @param string $task The task to analyze
     * @return array Task analysis with complexity, domain, requirements, etc.
     */
    private function analyzeTask(string $task): array
    {
        try {
            $prompt = <<<PROMPT
                Analyze this task and provide a structured assessment:

                Task: "{$task}"

                Provide analysis in JSON format with these fields:
                {
                  "complexity": "simple|medium|complex|extreme",
                  "domain": "general|technical|creative|analytical|conversational|monitoring",
                  "requires_tools": true|false,
                  "requires_quality": "standard|high|extreme",
                  "requires_knowledge": true|false,
                  "requires_reasoning": true|false,
                  "requires_iteration": true|false,
                  "estimated_steps": <number>,
                  "key_requirements": ["requirement1", "requirement2"]
                }

                Respond with ONLY the JSON, no explanation.
                PROMPT;

            $response = $this->client->messages()->create([
                'model' => 'claude-sonnet-4-5',
                'max_tokens' => 512,
                'system' => 'You are a task analysis system. Respond with JSON only.',
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

            $json = $this->extractTextContent($response->content ?? []);
            $json = preg_replace('/```(?:json)?\s*/', '', $json);
            $json = trim($json);

            $analysis = json_decode($json, true);

            if (is_array($analysis)) {
                return $analysis;
            }
        } catch (\Throwable $e) {
            $this->logger->warning("Task analysis failed, using defaults: {$e->getMessage()}");
        }

        // Fallback to simple analysis
        return [
            'complexity' => 'medium',
            'domain' => 'general',
            'requires_tools' => false,
            'requires_quality' => 'standard',
            'requires_knowledge' => false,
            'requires_reasoning' => false,
            'requires_iteration' => false,
            'estimated_steps' => 10,
            'key_requirements' => [],
        ];
    }

    /**
     * Select the best agent for the task based on analysis and previous attempts.
     *
     * @param array $taskAnalysis Task analysis data
     * @param array $previousAttempts Previous attempts (to avoid repeating failures)
     * @return string|null Agent ID or null if none suitable
     */
    private function selectBestAgent(array $taskAnalysis, array $previousAttempts): ?string
    {
        $scores = [];
        $triedAgents = array_column($previousAttempts, 'agent_id');

        foreach ($this->agents as $agentId => $agent) {
            // Skip agents we've already tried (unless all have been tried)
            if (in_array($agentId, $triedAgents) && count($triedAgents) < count($this->agents)) {
                continue;
            }

            $profile = $this->agentProfiles[$agentId];
            $perf = $this->performance[$agentId];

            $score = 0;

            // Match complexity level
            $complexityMatch = [
                'simple' => ['simple' => 10, 'medium' => 5, 'complex' => 2, 'extreme' => 1],
                'medium' => ['simple' => 5, 'medium' => 10, 'complex' => 7, 'extreme' => 3],
                'complex' => ['simple' => 2, 'medium' => 5, 'complex' => 10, 'extreme' => 8],
                'extreme' => ['simple' => 1, 'medium' => 2, 'complex' => 5, 'extreme' => 10],
            ];
            $score += $complexityMatch[$taskAnalysis['complexity']][$profile['complexity_level']] ?? 0;

            // Match quality requirements
            $qualityMatch = [
                'standard' => ['standard' => 10, 'high' => 5, 'extreme' => 3],
                'high' => ['standard' => 3, 'high' => 10, 'extreme' => 7],
                'extreme' => ['standard' => 1, 'high' => 5, 'extreme' => 10],
            ];
            $score += $qualityMatch[$taskAnalysis['requires_quality']][$profile['quality']] ?? 0;

            // Boost score based on agent's past performance
            if ($perf['attempts'] > 0) {
                $successRate = $perf['successes'] / $perf['attempts'];
                $score += $successRate * 5;
                $score += ($perf['average_quality'] / 10) * 3;
            }

            // Specific agent type bonuses
            if ($taskAnalysis['requires_tools'] && $profile['type'] === 'react') {
                $score += 5;
            }
            if ($taskAnalysis['requires_quality'] === 'extreme' && $profile['type'] === 'reflection') {
                $score += 5;
            }
            if ($taskAnalysis['requires_quality'] === 'extreme' && $profile['type'] === 'maker') {
                $score += 7;
            }
            if ($taskAnalysis['requires_knowledge'] && $profile['type'] === 'rag') {
                $score += 5;
            }
            if ($taskAnalysis['requires_reasoning'] && in_array($profile['type'], ['cot', 'tot'])) {
                $score += 5;
            }
            if ($taskAnalysis['domain'] === 'conversational' && $profile['type'] === 'dialog') {
                $score += 5;
            }

            // Penalty for retrying the same agent
            if (in_array($agentId, $triedAgents)) {
                $score -= 10;
            }

            $scores[$agentId] = $score;
        }

        if (empty($scores)) {
            return null;
        }

        // Return the agent with the highest score
        arsort($scores);
        $selected = array_key_first($scores);

        $this->logger->debug('Agent scores', $scores);

        return $selected;
    }

    /**
     * Execute the task with the specified agent.
     *
     * @param string $agentId Agent identifier
     * @param string $task The task to execute
     * @return AgentResult The result from the agent
     */
    private function executeWithAgent(string $agentId, string $task): AgentResult
    {
        $agent = $this->agents[$agentId];

        try {
            return $agent->run($task);
        } catch (\Throwable $e) {
            $this->logger->error("Agent {$agentId} threw exception: {$e->getMessage()}");

            return AgentResult::failure(
                error: "Agent execution failed: {$e->getMessage()}",
                metadata: ['agent_id' => $agentId]
            );
        }
    }

    /**
     * Validate the result quality and correctness.
     *
     * @param string $task Original task
     * @param AgentResult $result The result to validate
     * @param array $taskAnalysis Task analysis data
     * @return array Validation data with quality score and issues
     */
    private function validateResult(string $task, AgentResult $result, array $taskAnalysis): array
    {
        // If the result failed, it's automatically low quality
        if (! $result->isSuccess()) {
            return [
                'quality_score' => 0.0,
                'is_correct' => false,
                'is_complete' => false,
                'issues' => ['Agent execution failed: ' . ($result->getError() ?? 'Unknown error')],
            ];
        }

        try {
            $answer = $result->getAnswer();

            $prompt = <<<PROMPT
                Evaluate this agent's response for quality and correctness.

                Original Task: "{$task}"

                Agent's Answer: "{$answer}"

                Evaluate on these criteria:
                1. Correctness: Does it answer the task correctly?
                2. Completeness: Is the answer complete?
                3. Clarity: Is it clear and well-structured?
                4. Relevance: Is it relevant to the task?

                Provide evaluation in JSON format:
                {
                  "quality_score": <0-10>,
                  "is_correct": true|false,
                  "is_complete": true|false,
                  "issues": ["issue1", "issue2"],
                  "strengths": ["strength1", "strength2"]
                }

                Respond with ONLY the JSON, no explanation.
                PROMPT;

            $response = $this->client->messages()->create([
                'model' => 'claude-sonnet-4-5',
                'max_tokens' => 512,
                'system' => 'You are a result validation system. Be critical and objective. Respond with JSON only.',
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

            $json = $this->extractTextContent($response->content ?? []);
            $json = preg_replace('/```(?:json)?\s*/', '', $json);
            $json = trim($json);

            $validation = json_decode($json, true);

            if (is_array($validation)) {
                return [
                    'quality_score' => (float) ($validation['quality_score'] ?? 5.0),
                    'is_correct' => (bool) ($validation['is_correct'] ?? false),
                    'is_complete' => (bool) ($validation['is_complete'] ?? false),
                    'issues' => $validation['issues'] ?? [],
                    'strengths' => $validation['strengths'] ?? [],
                ];
            }
        } catch (\Throwable $e) {
            $this->logger->warning("Validation failed, using basic check: {$e->getMessage()}");
        }

        // Fallback validation
        $answerLength = strlen($result->getAnswer());
        $score = $answerLength > 50 ? 6.0 : 4.0;

        return [
            'quality_score' => $score,
            'is_correct' => true,
            'is_complete' => $answerLength > 50,
            'issues' => $answerLength < 50 ? ['Answer seems too short'] : [],
            'strengths' => [],
        ];
    }

    /**
     * Reframe the task to potentially get better results.
     *
     * @param string $originalTask Original task
     * @param array $issues Issues identified in validation
     * @return string Reframed task
     */
    private function reframeTask(string $originalTask, array $issues): string
    {
        try {
            $issuesText = implode("\n", array_map(fn ($i) => "- $i", $issues));

            $prompt = <<<PROMPT
                The following task was attempted but had quality issues. Reframe it to be clearer and more specific.

                Original Task: "{$originalTask}"

                Issues identified:
                {$issuesText}

                Provide a reframed version of the task that addresses these issues and is more likely to produce a better result.

                Respond with ONLY the reframed task, no explanation or additional text.
                PROMPT;

            $response = $this->client->messages()->create([
                'model' => 'claude-sonnet-4-5',
                'max_tokens' => 256,
                'system' => 'You are a task reframing assistant. Make tasks clearer and more specific.',
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

            $reframed = $this->extractTextContent($response->content ?? []);

            return trim($reframed);
        } catch (\Throwable $e) {
            $this->logger->warning("Task reframing failed: {$e->getMessage()}");

            return $originalTask . ' (Please provide a detailed and complete answer)';
        }
    }

    /**
     * Update performance metrics for an agent.
     *
     * @param string $agentId Agent identifier
     * @param float $qualityScore Quality score (0-10)
     * @param float $duration Execution duration in seconds
     */
    private function updatePerformance(string $agentId, float $qualityScore, float $duration): void
    {
        $perf = &$this->performance[$agentId];
        $perf['attempts']++;

        if ($qualityScore >= $this->qualityThreshold) {
            $perf['successes']++;
        } else {
            $perf['failures']++;
        }

        // Update running average of quality
        $totalAttempts = $perf['attempts'];
        $oldAvg = $perf['average_quality'];
        $perf['average_quality'] = ($oldAvg * ($totalAttempts - 1) + $qualityScore) / $totalAttempts;

        // Update total duration
        $perf['total_duration'] += $duration;
    }

    /**
     * Get the best attempt from all attempts.
     *
     * @param array $attempts All attempts
     * @return array|null Best attempt or null
     */
    private function getBestAttempt(array $attempts): ?array
    {
        if (empty($attempts)) {
            return null;
        }

        usort($attempts, function ($a, $b) {
            return $b['validation']['quality_score'] <=> $a['validation']['quality_score'];
        });

        return $attempts[0];
    }

    /**
     * Extract text content from Claude response.
     *
     * @param array $content Response content blocks
     * @return string Extracted text
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

    /**
     * Get the agent name.
     *
     * @return string Agent name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get performance metrics for all agents.
     *
     * @return array Performance data
     */
    public function getPerformance(): array
    {
        return $this->performance;
    }

    /**
     * Get registered agents.
     *
     * @return array Agent IDs
     */
    public function getRegisteredAgents(): array
    {
        return array_keys($this->agents);
    }

    /**
     * Get agent profile.
     *
     * @param string $agentId Agent identifier
     * @return array|null Agent profile or null
     */
    public function getAgentProfile(string $agentId): ?array
    {
        return $this->agentProfiles[$agentId] ?? null;
    }
}
