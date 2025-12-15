<?php

declare(strict_types=1);

namespace ClaudeAgents\Scheduling;

/**
 * Represents a scheduled task.
 */
class Task
{
    private string $id;
    private string $name;
    private $callback; // Callable - cannot use 'callable' as property type
    private ?Schedule $schedule;
    private array $dependencies;
    private array $metadata;
    private ?float $lastRun = null;
    private ?float $nextRun = null;
    private int $executionCount = 0;

    /**
     * @param string $name Task name
     * @param callable $callback Task callback
     * @param Schedule|null $schedule Schedule configuration
     * @param array<string> $dependencies Task dependencies (IDs)
     * @param array<string, mixed> $metadata Additional metadata
     */
    public function __construct(
        string $name,
        callable $callback,
        ?Schedule $schedule = null,
        array $dependencies = [],
        array $metadata = []
    ) {
        $this->id = uniqid('task_', true);
        $this->name = $name;
        $this->callback = $callback;
        $this->schedule = $schedule;
        $this->dependencies = $dependencies;
        $this->metadata = $metadata;

        if ($schedule) {
            $this->nextRun = $schedule->getNextRunTime();
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCallback(): callable
    {
        return $this->callback;
    }

    public function getSchedule(): ?Schedule
    {
        return $this->schedule;
    }

    /**
     * @return array<string>
     */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    public function getLastRun(): ?float
    {
        return $this->lastRun;
    }

    public function getNextRun(): ?float
    {
        return $this->nextRun;
    }

    public function getExecutionCount(): int
    {
        return $this->executionCount;
    }

    /**
     * Execute the task.
     */
    public function execute(): mixed
    {
        $this->lastRun = microtime(true);
        $this->executionCount++;

        $result = ($this->callback)();

        // Update next run time if recurring
        if ($this->schedule && $this->schedule->isRecurring()) {
            $this->nextRun = $this->schedule->getNextRunTime($this->lastRun);
        } else {
            $this->nextRun = null;
        }

        return $result;
    }

    public function isDue(?float $currentTime = null): bool
    {
        $currentTime ??= microtime(true);

        return $this->nextRun !== null && $this->nextRun <= $currentTime;
    }

    public function isRecurring(): bool
    {
        return $this->schedule && $this->schedule->isRecurring();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'schedule' => $this->schedule?->toString(),
            'dependencies' => $this->dependencies,
            'last_run' => $this->lastRun,
            'next_run' => $this->nextRun,
            'execution_count' => $this->executionCount,
            'metadata' => $this->metadata,
        ];
    }
}
