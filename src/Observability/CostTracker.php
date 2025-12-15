<?php

declare(strict_types=1);

namespace ClaudeAgents\Observability;

/**
 * Track costs with budgets, alerts, and breakdown reporting.
 */
class CostTracker
{
    private CostEstimator $estimator;

    /**
     * @var array<array{model: string, input_tokens: int, output_tokens: int, cost: float, timestamp: float}> Cost entries
     */
    private array $entries = [];

    /**
     * @var array<string, float> Costs by category (agent, tool, chain, etc.)
     */
    private array $costsByCategory = [];

    /**
     * @var array<string, float> Costs by model
     */
    private array $costsByModel = [];

    /**
     * @var float Budget limit in USD
     */
    private ?float $budget = null;

    /**
     * @var array<callable> Alert callbacks
     */
    private array $alertCallbacks = [];

    /**
     * @var array<float> Alert thresholds (percentage of budget)
     */
    private array $alertThresholds = [0.5, 0.75, 0.9, 1.0];

    /**
     * @var array<float> Triggered thresholds
     */
    private array $triggeredThresholds = [];

    public function __construct(?CostEstimator $estimator = null)
    {
        $this->estimator = $estimator ?? new CostEstimator();
    }

    /**
     * Record a cost entry.
     *
     * @param string $model Model used
     * @param int $inputTokens Input tokens
     * @param int $outputTokens Output tokens
     * @param string|null $category Optional category (agent, tool, etc.)
     */
    public function record(
        string $model,
        int $inputTokens,
        int $outputTokens,
        ?string $category = null,
    ): float {
        $cost = $this->estimator->estimateCost($model, $inputTokens, $outputTokens);

        $this->entries[] = [
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost' => $cost,
            'timestamp' => microtime(true),
            'category' => $category,
        ];

        // Track by model
        $this->costsByModel[$model] = ($this->costsByModel[$model] ?? 0) + $cost;

        // Track by category
        if ($category !== null) {
            $this->costsByCategory[$category] = ($this->costsByCategory[$category] ?? 0) + $cost;
        }

        // Check budget alerts
        $this->checkBudgetAlerts();

        return $cost;
    }

    /**
     * Set budget limit.
     *
     * @param float $budget Budget in USD
     */
    public function setBudget(float $budget): self
    {
        $this->budget = $budget;
        $this->triggeredThresholds = [];

        return $this;
    }

    /**
     * Get budget limit.
     */
    public function getBudget(): ?float
    {
        return $this->budget;
    }

    /**
     * Set alert thresholds.
     *
     * @param array<float> $thresholds Percentages (e.g., [0.5, 0.75, 0.9, 1.0] for 50%, 75%, 90%, 100%)
     */
    public function setAlertThresholds(array $thresholds): self
    {
        $this->alertThresholds = $thresholds;
        sort($this->alertThresholds);

        return $this;
    }

    /**
     * Add an alert callback.
     *
     * Callback receives: (float $totalCost, float $budget, float $threshold)
     */
    public function onAlert(callable $callback): self
    {
        $this->alertCallbacks[] = $callback;

        return $this;
    }

    /**
     * Get total cost.
     */
    public function getTotalCost(): float
    {
        return array_sum(array_column($this->entries, 'cost'));
    }

    /**
     * Get remaining budget.
     */
    public function getRemainingBudget(): ?float
    {
        if ($this->budget === null) {
            return null;
        }

        return max(0, $this->budget - $this->getTotalCost());
    }

    /**
     * Get budget usage percentage.
     */
    public function getBudgetUsagePercent(): ?float
    {
        if ($this->budget === null || $this->budget === 0.0) {
            return null;
        }

        return ($this->getTotalCost() / $this->budget) * 100;
    }

    /**
     * Check if budget is exceeded.
     */
    public function isBudgetExceeded(): bool
    {
        if ($this->budget === null) {
            return false;
        }

        return $this->getTotalCost() >= $this->budget;
    }

    /**
     * Get costs by model.
     *
     * @return array<string, float>
     */
    public function getCostsByModel(): array
    {
        return $this->costsByModel;
    }

    /**
     * Get costs by category.
     *
     * @return array<string, float>
     */
    public function getCostsByCategory(): array
    {
        return $this->costsByCategory;
    }

    /**
     * Get cost for a specific model.
     */
    public function getCostForModel(string $model): float
    {
        return $this->costsByModel[$model] ?? 0.0;
    }

    /**
     * Get cost for a specific category.
     */
    public function getCostForCategory(string $category): float
    {
        return $this->costsByCategory[$category] ?? 0.0;
    }

    /**
     * Get all entries.
     *
     * @return array<array{model: string, input_tokens: int, output_tokens: int, cost: float, timestamp: float}>
     */
    public function getEntries(): array
    {
        return $this->entries;
    }

    /**
     * Get entries within a time range.
     *
     * @param float $startTime Unix timestamp
     * @param float $endTime Unix timestamp
     * @return array<array{model: string, input_tokens: int, output_tokens: int, cost: float, timestamp: float}>
     */
    public function getEntriesInRange(float $startTime, float $endTime): array
    {
        return array_filter(
            $this->entries,
            fn ($entry) => $entry['timestamp'] >= $startTime && $entry['timestamp'] <= $endTime
        );
    }

    /**
     * Get cost summary.
     *
     * @return array<string, mixed>
     */
    public function getSummary(): array
    {
        $totalCost = $this->getTotalCost();
        $totalTokens = [
            'input' => array_sum(array_column($this->entries, 'input_tokens')),
            'output' => array_sum(array_column($this->entries, 'output_tokens')),
        ];

        return [
            'total_cost' => $totalCost,
            'total_cost_formatted' => CostEstimator::formatCost($totalCost),
            'total_tokens' => $totalTokens,
            'total_requests' => count($this->entries),
            'average_cost_per_request' => count($this->entries) > 0 ? $totalCost / count($this->entries) : 0,
            'by_model' => $this->costsByModel,
            'by_category' => $this->costsByCategory,
            'budget' => [
                'limit' => $this->budget,
                'remaining' => $this->getRemainingBudget(),
                'usage_percent' => $this->getBudgetUsagePercent(),
                'exceeded' => $this->isBudgetExceeded(),
            ],
        ];
    }

    /**
     * Export to CSV.
     */
    public function toCsv(): string
    {
        $lines = ['Timestamp,Model,Input Tokens,Output Tokens,Cost,Category'];

        foreach ($this->entries as $entry) {
            $lines[] = implode(',', [
                date('Y-m-d H:i:s', (int)$entry['timestamp']),
                $entry['model'],
                $entry['input_tokens'],
                $entry['output_tokens'],
                $entry['cost'],
                $entry['category'] ?? '',
            ]);
        }

        return implode("\n", $lines);
    }

    /**
     * Reset all tracked costs.
     */
    public function reset(): void
    {
        $this->entries = [];
        $this->costsByCategory = [];
        $this->costsByModel = [];
        $this->triggeredThresholds = [];
    }

    /**
     * Check budget alerts and trigger callbacks.
     */
    private function checkBudgetAlerts(): void
    {
        if ($this->budget === null || empty($this->alertCallbacks)) {
            return;
        }

        $totalCost = $this->getTotalCost();

        foreach ($this->alertThresholds as $threshold) {
            // Skip if already triggered
            if (in_array($threshold, $this->triggeredThresholds)) {
                continue;
            }

            $thresholdAmount = $this->budget * $threshold;

            if ($totalCost >= $thresholdAmount) {
                $this->triggeredThresholds[] = $threshold;

                foreach ($this->alertCallbacks as $callback) {
                    $callback($totalCost, $this->budget, $threshold);
                }
            }
        }
    }
}
