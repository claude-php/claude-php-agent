<?php

declare(strict_types=1);

namespace Tests\Unit\Observability;

use ClaudeAgents\Observability\CostTracker;
use PHPUnit\Framework\TestCase;

class CostTrackerTest extends TestCase
{
    public function testRecordCost(): void
    {
        $tracker = new CostTracker();

        $cost = $tracker->record('claude-sonnet-4-5', 1000, 500, 'agent');

        $this->assertGreaterThan(0, $cost);
        $this->assertEquals($cost, $tracker->getTotalCost());
        $this->assertEquals($cost, $tracker->getCostForCategory('agent'));
        $this->assertEquals($cost, $tracker->getCostForModel('claude-sonnet-4-5'));
    }

    public function testBudget(): void
    {
        $tracker = new CostTracker();
        $tracker->setBudget(10.0);

        $this->assertEquals(10.0, $tracker->getBudget());
        $this->assertEquals(10.0, $tracker->getRemainingBudget());
        $this->assertFalse($tracker->isBudgetExceeded());

        $tracker->record('claude-sonnet-4-5', 100_000, 50_000);

        $remaining = $tracker->getRemainingBudget();
        $this->assertLessThan(10.0, $remaining);
        $this->assertGreaterThan(0, $remaining);
    }

    public function testBudgetAlert(): void
    {
        $tracker = new CostTracker();
        $tracker->setBudget(0.01); // 1 cent
        $tracker->setAlertThresholds([0.5, 1.0]);

        $alertTriggered = false;
        $tracker->onAlert(function ($total, $budget, $threshold) use (&$alertTriggered) {
            $alertTriggered = true;
        });

        // This should trigger 50% alert
        $tracker->record('claude-sonnet-4-5', 1000, 500);

        $this->assertTrue($alertTriggered);
    }

    public function testGetSummary(): void
    {
        $tracker = new CostTracker();
        $tracker->setBudget(10.0);
        $tracker->record('claude-sonnet-4-5', 1000, 500, 'agent');
        $tracker->record('claude-haiku', 500, 250, 'tool');

        $summary = $tracker->getSummary();

        $this->assertArrayHasKey('total_cost', $summary);
        $this->assertArrayHasKey('total_tokens', $summary);
        $this->assertArrayHasKey('by_model', $summary);
        $this->assertArrayHasKey('by_category', $summary);
        $this->assertArrayHasKey('budget', $summary);
        $this->assertEquals(2, $summary['total_requests']);
    }

    public function testToCsv(): void
    {
        $tracker = new CostTracker();
        $tracker->record('claude-sonnet-4-5', 1000, 500, 'agent');

        $csv = $tracker->toCsv();

        $this->assertStringContainsString('Timestamp,Model,Input Tokens', $csv);
        $this->assertStringContainsString('claude-sonnet-4-5', $csv);
        $this->assertStringContainsString('1000', $csv);
    }

    public function testReset(): void
    {
        $tracker = new CostTracker();
        $tracker->record('claude-sonnet-4-5', 1000, 500);

        $tracker->reset();

        $this->assertEquals(0, $tracker->getTotalCost());
        $this->assertEmpty($tracker->getEntries());
    }
}
