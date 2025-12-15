<?php

declare(strict_types=1);

namespace Tests\Unit\Observability;

use ClaudeAgents\Observability\MetricsAggregator;
use PHPUnit\Framework\TestCase;

class MetricsAggregatorTest extends TestCase
{
    public function testRecordDuration(): void
    {
        $aggregator = new MetricsAggregator();
        $aggregator->recordDuration(100);
        $aggregator->recordDuration(200);
        $aggregator->recordDuration(300);

        $stats = $aggregator->getDurationStats();

        $this->assertEquals(3, $stats['count']);
        $this->assertEquals(600, $stats['sum']);
        $this->assertEquals(200, $stats['mean']);
        $this->assertEquals(100, $stats['min']);
        $this->assertEquals(300, $stats['max']);
    }

    public function testPercentiles(): void
    {
        $aggregator = new MetricsAggregator();

        for ($i = 1; $i <= 100; $i++) {
            $aggregator->recordDuration($i);
        }

        $percentiles = $aggregator->getDurationPercentiles([50, 95, 99]);

        $this->assertEqualsWithDelta(50, $percentiles['p50'], 1);
        $this->assertEqualsWithDelta(95, $percentiles['p95'], 1);
        $this->assertEqualsWithDelta(99, $percentiles['p99'], 1);
    }

    public function testHistogram(): void
    {
        $aggregator = new MetricsAggregator();
        $aggregator->recordDuration(25);
        $aggregator->recordDuration(75);
        $aggregator->recordDuration(150);
        $aggregator->recordDuration(500);

        $histogram = $aggregator->getDurationHistogram([50, 100, 250, 1000]);

        $this->assertEquals(1, $histogram['buckets']['50ms']);
        $this->assertEquals(1, $histogram['buckets']['100ms']);
        $this->assertEquals(1, $histogram['buckets']['250ms']);
        $this->assertEquals(1, $histogram['buckets']['1000ms']);
    }

    public function testRequestRate(): void
    {
        $aggregator = new MetricsAggregator(1); // 1 second window

        $aggregator->recordRequest(true);
        $aggregator->recordRequest(true);
        $aggregator->recordRequest(false);

        $rate = $aggregator->getRequestRate();
        $this->assertGreaterThan(0, $rate);

        $successRate = $aggregator->getSuccessRate();
        $this->assertEqualsWithDelta(66.67, $successRate, 0.1);
    }

    public function testCountersAndGauges(): void
    {
        $aggregator = new MetricsAggregator();

        $aggregator->incrementCounter('requests');
        $aggregator->incrementCounter('requests', 5);
        $aggregator->setGauge('memory', 1024.5);

        $this->assertEquals(6, $aggregator->getCounter('requests'));
        $this->assertEquals(1024.5, $aggregator->getGauge('memory'));
    }

    public function testPrometheusExport(): void
    {
        $aggregator = new MetricsAggregator();
        $aggregator->incrementCounter('requests', 10);
        $aggregator->setGauge('memory_mb', 512);
        $aggregator->recordDuration(100);

        $prometheus = $aggregator->toPrometheus('test');

        $this->assertStringContainsString('test_requests 10', $prometheus);
        $this->assertStringContainsString('test_memory_mb 512', $prometheus);
        $this->assertStringContainsString('TYPE test_duration_ms histogram', $prometheus);
    }
}
