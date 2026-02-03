<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Services;

use ClaudeAgents\Services\Settings\SettingsService;
use ClaudeAgents\Services\Telemetry\TelemetryService;
use PHPUnit\Framework\TestCase;

class TelemetryServiceTest extends TestCase
{
    private TelemetryService $service;

    protected function setUp(): void
    {
        $settings = new SettingsService(null, [
            'telemetry.enabled' => true,
        ]);
        $settings->initialize();

        $this->service = new TelemetryService($settings);
        $this->service->initialize();
    }

    public function testGetName(): void
    {
        $this->assertSame('telemetry', $this->service->getName());
    }

    public function testRecordCounter(): void
    {
        $this->service->recordCounter('test.counter', 1);
        $this->service->recordCounter('test.counter', 2);

        $metrics = $this->service->getAllMetrics();

        $this->assertArrayHasKey('test.counter', $metrics['counters']);
        $this->assertSame(3, $metrics['counters']['test.counter']);
    }

    public function testRecordGauge(): void
    {
        $this->service->recordGauge('test.gauge', 100.5);
        $this->service->recordGauge('test.gauge', 200.5);

        $metrics = $this->service->getAllMetrics();

        $this->assertArrayHasKey('test.gauge', $metrics['gauges']);
        $this->assertSame(200.5, $metrics['gauges']['test.gauge']); // Latest value
    }

    public function testRecordHistogram(): void
    {
        $this->service->recordHistogram('test.histogram', 10.0);
        $this->service->recordHistogram('test.histogram', 20.0);
        $this->service->recordHistogram('test.histogram', 30.0);

        $metrics = $this->service->getAllMetrics();

        $this->assertArrayHasKey('test.histogram', $metrics['histograms']);
        $histogram = $metrics['histograms']['test.histogram'];

        $this->assertSame(3, $histogram['count']);
        $this->assertSame(60.0, $histogram['sum']);
        $this->assertSame(10.0, $histogram['min']);
        $this->assertSame(30.0, $histogram['max']);
        $this->assertSame(20.0, $histogram['avg']);
    }

    public function testRecordAgentRequest(): void
    {
        $this->service->recordAgentRequest(
            success: true,
            tokensInput: 100,
            tokensOutput: 50,
            duration: 250.5
        );

        $summary = $this->service->getSummary();

        $this->assertSame(1, $summary['total_requests']);
        $this->assertSame(1, $summary['successful_requests']);
        $this->assertSame(100, $summary['total_tokens']['input']);
        $this->assertSame(50, $summary['total_tokens']['output']);
    }

    public function testRecordFailedAgentRequest(): void
    {
        $this->service->recordAgentRequest(
            success: false,
            tokensInput: 0,
            tokensOutput: 0,
            duration: 100.0,
            error: 'Test error'
        );

        $summary = $this->service->getSummary();

        $this->assertSame(1, $summary['total_requests']);
        $this->assertSame(1, $summary['failed_requests']);
        $this->assertSame(0.0, $summary['success_rate']);
    }

    public function testReset(): void
    {
        $this->service->recordCounter('test.counter', 1);
        $this->service->recordGauge('test.gauge', 100.0);

        $this->service->reset();

        $metrics = $this->service->getAllMetrics();

        $this->assertEmpty($metrics['counters']);
        $this->assertEmpty($metrics['gauges']);
        $this->assertEmpty($metrics['histograms']);
    }

    public function testIsEnabled(): void
    {
        $this->assertTrue($this->service->isEnabled());
    }

    public function testMetricsWithAttributes(): void
    {
        $this->service->recordCounter('api.requests', 1, ['endpoint' => '/users']);
        $this->service->recordCounter('api.requests', 1, ['endpoint' => '/posts']);
        $this->service->recordCounter('api.requests', 1, ['endpoint' => '/users']);

        $metrics = $this->service->getAllMetrics();

        // Should have separate counters for each attribute combination
        $this->assertArrayHasKey('api.requests|endpoint=/users', $metrics['counters']);
        $this->assertArrayHasKey('api.requests|endpoint=/posts', $metrics['counters']);
        $this->assertSame(2, $metrics['counters']['api.requests|endpoint=/users']);
        $this->assertSame(1, $metrics['counters']['api.requests|endpoint=/posts']);
    }
}
