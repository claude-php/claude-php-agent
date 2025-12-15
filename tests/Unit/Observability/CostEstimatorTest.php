<?php

declare(strict_types=1);

namespace Tests\Unit\Observability;

use ClaudeAgents\Observability\CostEstimator;
use PHPUnit\Framework\TestCase;

class CostEstimatorTest extends TestCase
{
    public function testEstimateCost(): void
    {
        $estimator = new CostEstimator();

        // Claude Sonnet: $3 per 1M input, $15 per 1M output
        $cost = $estimator->estimateCost('claude-sonnet-4-5', 1000, 500);

        $expectedCost = (1000 * 3 + 500 * 15) / 1_000_000;
        $this->assertEquals($expectedCost, $cost);
    }

    public function testGetPricing(): void
    {
        $estimator = new CostEstimator();

        $pricing = $estimator->getPricing('claude-sonnet-4-5');

        $this->assertIsArray($pricing);
        $this->assertArrayHasKey('input', $pricing);
        $this->assertArrayHasKey('output', $pricing);
        $this->assertEquals(3.0, $pricing['input']);
        $this->assertEquals(15.0, $pricing['output']);
    }

    public function testPartialModelMatch(): void
    {
        $estimator = new CostEstimator();

        $pricing = $estimator->getPricing('claude-sonnet-20241022');

        $this->assertIsArray($pricing);
        $this->assertEquals(3.0, $pricing['input']);
    }

    public function testUnknownModelFallback(): void
    {
        $estimator = new CostEstimator();

        $cost = $estimator->estimateCost('unknown-model', 1000, 500);

        $this->assertGreaterThan(0, $cost);
    }

    public function testFormatCost(): void
    {
        $this->assertEquals('50.00µ$', CostEstimator::formatCost(0.00005));
        $this->assertEquals('5.00m$', CostEstimator::formatCost(0.005));
        $this->assertEquals('$0.0500', CostEstimator::formatCost(0.05));
        $this->assertEquals('$1.2345', CostEstimator::formatCost(1.2345));
    }

    public function testGetSupportedModels(): void
    {
        $estimator = new CostEstimator();

        $models = $estimator->getSupportedModels();

        $this->assertIsArray($models);
        $this->assertContains('claude-sonnet-4-5', $models);
        $this->assertContains('claude-opus', $models);
        $this->assertContains('claude-haiku', $models);
    }
}
