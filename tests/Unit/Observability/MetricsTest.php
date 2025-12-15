<?php

declare(strict_types=1);

namespace Tests\Unit\Observability;

use ClaudeAgents\Observability\Metrics;
use PHPUnit\Framework\TestCase;

class MetricsTest extends TestCase
{
    public function testRecordSuccessfulRequest(): void
    {
        $metrics = new Metrics();
        $metrics->recordRequest(true, 100, 50, 123.45);

        $summary = $metrics->getSummary();

        $this->assertEquals(1, $summary['total_requests']);
        $this->assertEquals(1, $summary['successful_requests']);
        $this->assertEquals(0, $summary['failed_requests']);
        $this->assertEquals(1.0, $summary['success_rate']);
        $this->assertEquals(100, $summary['total_tokens']['input']);
        $this->assertEquals(50, $summary['total_tokens']['output']);
    }

    public function testRecordFailedRequest(): void
    {
        $metrics = new Metrics();
        $metrics->recordRequest(false, 0, 0, 100, 'API Error');

        $summary = $metrics->getSummary();

        $this->assertEquals(1, $summary['total_requests']);
        $this->assertEquals(0, $summary['successful_requests']);
        $this->assertEquals(1, $summary['failed_requests']);
        $this->assertEquals(0, $summary['success_rate']);
        $this->assertArrayHasKey('API', $summary['error_counts']);
    }

    public function testAverageDuration(): void
    {
        $metrics = new Metrics();
        $metrics->recordRequest(true, 100, 50, 100);
        $metrics->recordRequest(true, 100, 50, 200);

        $summary = $metrics->getSummary();

        $this->assertEquals(300, $summary['total_duration_ms']);
        $this->assertEquals(150, $summary['average_duration_ms']);
    }

    public function testReset(): void
    {
        $metrics = new Metrics();
        $metrics->recordRequest(true, 100, 50, 100);

        $metrics->reset();
        $summary = $metrics->getSummary();

        $this->assertEquals(0, $summary['total_requests']);
        $this->assertEquals(0, $summary['total_tokens']['total']);
    }
}
