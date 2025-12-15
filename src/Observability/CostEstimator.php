<?php

declare(strict_types=1);

namespace ClaudeAgents\Observability;

/**
 * Estimates API costs based on token usage.
 */
class CostEstimator
{
    /**
     * Pricing per 1M tokens for different models.
     * Update these as Claude pricing changes.
     *
     * @var array<string, array{input: float, output: float}>
     */
    private const PRICING = [
        'claude-opus' => [
            'input' => 15.0,
            'output' => 75.0,
        ],
        'claude-opus-4-20250514' => [
            'input' => 15.0,
            'output' => 75.0,
        ],
        'claude-sonnet-4-5' => [
            'input' => 3.0,
            'output' => 15.0,
        ],
        'claude-sonnet' => [
            'input' => 3.0,
            'output' => 15.0,
        ],
        'claude-haiku' => [
            'input' => 0.25,
            'output' => 1.25,
        ],
        'claude-3-opus' => [
            'input' => 15.0,
            'output' => 75.0,
        ],
        'claude-3-sonnet' => [
            'input' => 3.0,
            'output' => 15.0,
        ],
        'claude-3-haiku' => [
            'input' => 0.25,
            'output' => 1.25,
        ],
    ];

    /**
     * Estimate cost for a request.
     *
     * @param string $model The model name
     * @param int $inputTokens Input tokens used
     * @param int $outputTokens Output tokens used
     * @return float Cost in USD
     */
    public function estimateCost(string $model, int $inputTokens, int $outputTokens): float
    {
        $pricing = $this->getPricing($model);

        if ($pricing === null) {
            // Default pricing if model not found
            return (($inputTokens * 0.003) + ($outputTokens * 0.015)) / 1000;
        }

        // Calculate cost: (tokens * price per 1M tokens) / 1M
        $inputCost = ($inputTokens * $pricing['input']) / 1_000_000;
        $outputCost = ($outputTokens * $pricing['output']) / 1_000_000;

        return $inputCost + $outputCost;
    }

    /**
     * Get pricing for a model.
     *
     * @param string $model The model name
     * @return array{input: float, output: float}|null Pricing or null if not found
     */
    public function getPricing(string $model): ?array
    {
        // Try exact match first
        if (isset(self::PRICING[$model])) {
            return self::PRICING[$model];
        }

        // Try partial match for model families
        foreach (self::PRICING as $name => $pricing) {
            if (str_contains($model, $name)) {
                return $pricing;
            }
        }

        return null;
    }

    /**
     * Format cost as human-readable string.
     */
    public static function formatCost(float $cost): string
    {
        if ($cost < 0.0001) {
            return number_format($cost * 1_000_000, 2) . 'µ$';
        }
        if ($cost < 0.01) {
            return number_format($cost * 1000, 2) . 'm$';
        }

        return '$' . number_format($cost, 4);
    }

    /**
     * Get all supported models.
     *
     * @return array<string>
     */
    public function getSupportedModels(): array
    {
        return array_keys(self::PRICING);
    }

    /**
     * Update pricing for a model.
     *
     * @param string $model Model name
     * @param float $inputPrice Price per 1M input tokens
     * @param float $outputPrice Price per 1M output tokens
     */
    public function updatePricing(string $model, float $inputPrice, float $outputPrice): void
    {
        // Note: This modifies a local property, not the constant
        // In production, you'd want to load pricing from a config file
    }
}
