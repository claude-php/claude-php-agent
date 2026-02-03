<?php

declare(strict_types=1);

namespace ClaudeAgents\Execution;

/**
 * Real-time progress tracking for flow execution.
 *
 * Tracks iterations, steps, duration, and completion percentage
 * for agent flow execution.
 *
 * @example
 * ```php
 * $progress = new FlowProgress(totalIterations: 10);
 * $progress->startIteration(1);
 * $progress->completeStep('tool_execution');
 * echo $progress->getProgress(); // 10.0
 * ```
 */
class FlowProgress
{
    private int $currentIteration = 0;
    private int $totalIterations;
    private ?float $startTime = null;
    private ?float $endTime = null;
    private string $currentStep = 'initializing';

    /**
     * @var array<string, array{started: float, completed: ?float}>
     */
    private array $completedSteps = [];

    /**
     * @var array<string, mixed>
     */
    private array $metadata = [];

    /**
     * @param int $totalIterations Expected total iterations
     * @param array<string, mixed> $metadata Additional metadata
     */
    public function __construct(int $totalIterations, array $metadata = [])
    {
        $this->totalIterations = $totalIterations;
        $this->metadata = $metadata;
    }

    /**
     * Start tracking.
     */
    public function start(): self
    {
        $this->startTime = microtime(true);
        $this->currentStep = 'started';
        return $this;
    }

    /**
     * Mark as completed.
     */
    public function complete(): self
    {
        $this->endTime = microtime(true);
        $this->currentStep = 'completed';
        return $this;
    }

    /**
     * Start a new iteration.
     */
    public function startIteration(int $iteration): self
    {
        $this->currentIteration = $iteration;
        $this->currentStep = "iteration_{$iteration}";
        return $this;
    }

    /**
     * Complete a step within the current iteration.
     */
    public function completeStep(string $stepName): self
    {
        if (!isset($this->completedSteps[$stepName])) {
            $this->completedSteps[$stepName] = [
                'started' => microtime(true),
                'completed' => null,
            ];
        }

        $this->completedSteps[$stepName]['completed'] = microtime(true);
        $this->currentStep = $stepName;

        return $this;
    }

    /**
     * Start a named step.
     */
    public function startStep(string $stepName): self
    {
        $this->completedSteps[$stepName] = [
            'started' => microtime(true),
            'completed' => null,
        ];
        $this->currentStep = $stepName;

        return $this;
    }

    /**
     * Update the current step description.
     */
    public function updateStep(string $stepName): self
    {
        $this->currentStep = $stepName;
        return $this;
    }

    /**
     * Get progress percentage (0-100).
     */
    public function getProgress(): float
    {
        if ($this->totalIterations === 0) {
            return 100.0;
        }

        return ($this->currentIteration / $this->totalIterations) * 100;
    }

    /**
     * Get the current iteration number.
     */
    public function getCurrentIteration(): int
    {
        return $this->currentIteration;
    }

    /**
     * Get the total iterations.
     */
    public function getTotalIterations(): int
    {
        return $this->totalIterations;
    }

    /**
     * Get the current step name.
     */
    public function getCurrentStep(): string
    {
        return $this->currentStep;
    }

    /**
     * Get elapsed duration in seconds.
     */
    public function getDuration(): float
    {
        if ($this->startTime === null) {
            return 0.0;
        }

        $endTime = $this->endTime ?? microtime(true);
        return $endTime - $this->startTime;
    }

    /**
     * Get formatted duration string.
     */
    public function getFormattedDuration(): string
    {
        $duration = $this->getDuration();

        if ($duration < 1) {
            return round($duration * 1000) . ' ms';
        }

        if ($duration < 60) {
            return round($duration, 1) . ' s';
        }

        $minutes = floor($duration / 60);
        $seconds = $duration % 60;
        return "{$minutes}m " . round($seconds, 1) . 's';
    }

    /**
     * Check if execution is complete.
     */
    public function isComplete(): bool
    {
        return $this->endTime !== null || $this->currentIteration >= $this->totalIterations;
    }

    /**
     * Check if execution has started.
     */
    public function isStarted(): bool
    {
        return $this->startTime !== null;
    }

    /**
     * Get all completed steps.
     *
     * @return array<string, array{started: float, completed: ?float, duration: ?float}>
     */
    public function getCompletedSteps(): array
    {
        $steps = [];

        foreach ($this->completedSteps as $name => $times) {
            $steps[$name] = [
                'started' => $times['started'],
                'completed' => $times['completed'],
                'duration' => $times['completed'] !== null
                    ? $times['completed'] - $times['started']
                    : null,
            ];
        }

        return $steps;
    }

    /**
     * Get step count.
     */
    public function getStepCount(): int
    {
        return count($this->completedSteps);
    }

    /**
     * Get completed step count.
     */
    public function getCompletedStepCount(): int
    {
        return count(array_filter($this->completedSteps, fn($step) => $step['completed'] !== null));
    }

    /**
     * Estimate time remaining in seconds.
     */
    public function getEstimatedTimeRemaining(): ?float
    {
        if ($this->currentIteration === 0 || !$this->isStarted()) {
            return null;
        }

        $avgTimePerIteration = $this->getDuration() / $this->currentIteration;
        $remainingIterations = $this->totalIterations - $this->currentIteration;

        return $avgTimePerIteration * $remainingIterations;
    }

    /**
     * Set metadata value.
     */
    public function setMetadata(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    /**
     * Get metadata value.
     */
    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Get all metadata.
     *
     * @return array<string, mixed>
     */
    public function getAllMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Convert progress to array format.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'current_iteration' => $this->currentIteration,
            'total_iterations' => $this->totalIterations,
            'progress_percent' => $this->getProgress(),
            'current_step' => $this->currentStep,
            'duration' => $this->getDuration(),
            'formatted_duration' => $this->getFormattedDuration(),
            'estimated_remaining' => $this->getEstimatedTimeRemaining(),
            'is_complete' => $this->isComplete(),
            'is_started' => $this->isStarted(),
            'completed_steps' => $this->getCompletedSteps(),
            'step_count' => $this->getStepCount(),
            'completed_step_count' => $this->getCompletedStepCount(),
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Get a summary string.
     */
    public function getSummary(): string
    {
        $progress = round($this->getProgress(), 1);
        $duration = $this->getFormattedDuration();

        return "Progress: {$progress}% ({$this->currentIteration}/{$this->totalIterations}) - " .
               "Duration: {$duration} - Step: {$this->currentStep}";
    }
}
