<?php

declare(strict_types=1);

namespace ClaudeAgents\ML\Traits;

use ClaudeAgents\AgentResult;
use ClaudeAgents\ML\TaskEmbedder;
use ClaudeAgents\ML\TaskHistoryStore;

/**
 * Trait for adding learning capabilities to any agent.
 *
 * Provides automatic performance tracking and learning from execution history.
 * Any agent can become "learnable" by using this trait.
 *
 * @package ClaudeAgents\ML\Traits
 */
trait LearnableAgent
{
    private ?TaskHistoryStore $learningHistory = null;
    private ?TaskEmbedder $learningEmbedder = null;
    private bool $learningEnabled = false;

    /**
     * Enable learning for this agent.
     *
     * @param string $historyPath Path to store learning history
     * @return self
     */
    public function enableLearning(string $historyPath = 'storage/agent_learning.json'): self
    {
        $this->learningHistory = new TaskHistoryStore($historyPath);
        $this->learningEmbedder = new TaskEmbedder();
        $this->learningEnabled = true;

        return $this;
    }

    /**
     * Disable learning for this agent.
     *
     * @return self
     */
    public function disableLearning(): self
    {
        $this->learningEnabled = false;

        return $this;
    }

    /**
     * Check if learning is enabled.
     *
     * @return bool
     */
    public function isLearningEnabled(): bool
    {
        return $this->learningEnabled;
    }

    /**
     * Record a task execution for learning.
     *
     * @param string $task The task that was executed
     * @param AgentResult $result The execution result
     * @param array $metadata Additional metadata to store
     */
    protected function recordExecution(
        string $task,
        AgentResult $result,
        array $metadata = []
    ): void {
        if (!$this->learningEnabled || !$this->learningHistory || !$this->learningEmbedder) {
            return;
        }

        try {
            $taskAnalysis = $this->analyzeTaskForLearning($task);
            $taskVector = $this->learningEmbedder->embed($taskAnalysis);

            $this->learningHistory->record([
                'id' => uniqid('exec_', true),
                'task' => substr($task, 0, 500), // Limit task length
                'task_vector' => $taskVector,
                'task_analysis' => $taskAnalysis,
                'agent_id' => $this->getAgentIdentifier(),
                'agent_type' => $this->getAgentType(),
                'success' => $result->isSuccess(),
                'quality_score' => $this->evaluateResultQuality($result),
                'duration' => $metadata['duration'] ?? 0.0,
                'iterations' => $result->getIterations(),
                'timestamp' => time(),
                'metadata' => $metadata,
            ]);
        } catch (\Throwable $e) {
            // Silently fail - learning shouldn't break execution
            if (method_exists($this, 'logWarning')) {
                $this->logWarning("Failed to record execution: {$e->getMessage()}");
            }
        }
    }

    /**
     * Get historical performance for similar tasks.
     *
     * @param string $task Task to find similar history for
     * @param int $k Number of similar tasks to retrieve
     * @return array Historical performance data
     */
    protected function getHistoricalPerformance(string $task, int $k = 10): array
    {
        if (!$this->learningEnabled || !$this->learningHistory || !$this->learningEmbedder) {
            return [];
        }

        $taskAnalysis = $this->analyzeTaskForLearning($task);
        $taskVector = $this->learningEmbedder->embed($taskAnalysis);

        return $this->learningHistory->findSimilar($taskVector, $k);
    }

    /**
     * Get learning statistics.
     *
     * @return array Statistics about learning history
     */
    public function getLearningStats(): array
    {
        if (!$this->learningEnabled || !$this->learningHistory) {
            return ['learning_enabled' => false];
        }

        return $this->learningHistory->getStats();
    }

    /**
     * Get the learning history store.
     *
     * @return TaskHistoryStore|null
     */
    public function getLearningHistory(): ?TaskHistoryStore
    {
        return $this->learningHistory;
    }

    /**
     * Analyze task for learning purposes.
     *
     * Override this method to customize task analysis for your agent.
     *
     * @param string $task The task to analyze
     * @return array Task analysis data
     */
    protected function analyzeTaskForLearning(string $task): array
    {
        // Default simple analysis
        return [
            'complexity' => $this->estimateComplexity($task),
            'domain' => 'general',
            'requires_tools' => false,
            'requires_knowledge' => false,
            'requires_reasoning' => true,
            'requires_iteration' => false,
            'requires_quality' => 'standard',
            'estimated_steps' => 5,
            'key_requirements' => [],
        ];
    }

    /**
     * Evaluate result quality.
     *
     * Override this method to customize quality evaluation for your agent.
     *
     * @param AgentResult $result The result to evaluate
     * @return float Quality score (0-10)
     */
    protected function evaluateResultQuality(AgentResult $result): float
    {
        if (!$result->isSuccess()) {
            return 0.0;
        }

        // Default: use answer length as rough quality proxy
        $answerLength = strlen($result->getAnswer());

        if ($answerLength < 50) {
            return 4.0;
        } elseif ($answerLength < 200) {
            return 6.0;
        } elseif ($answerLength < 500) {
            return 7.5;
        } else {
            return 8.5;
        }
    }

    /**
     * Estimate task complexity.
     *
     * @param string $task The task
     * @return string Complexity level
     */
    private function estimateComplexity(string $task): string
    {
        $length = strlen($task);
        $wordCount = str_word_count($task);

        if ($length < 50 || $wordCount < 10) {
            return 'simple';
        } elseif ($length < 200 || $wordCount < 30) {
            return 'medium';
        } else {
            return 'complex';
        }
    }

    /**
     * Get agent identifier for learning.
     *
     * Override if your agent has a specific name property.
     *
     * @return string
     */
    protected function getAgentIdentifier(): string
    {
        if (method_exists($this, 'getName')) {
            return $this->getName();
        }

        return get_class($this);
    }

    /**
     * Get agent type for learning.
     *
     * Override to provide specific agent type.
     *
     * @return string
     */
    protected function getAgentType(): string
    {
        $className = get_class($this);
        $shortName = substr($className, strrpos($className, '\\') + 1);

        return strtolower(str_replace('Agent', '', $shortName));
    }
}

