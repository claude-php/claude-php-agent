<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Services;

use ClaudeAgents\Services\Settings\SettingsService;
use ClaudeAgents\Services\Tracing\TracingService;
use PHPUnit\Framework\TestCase;

class TracingServiceTest extends TestCase
{
    private TracingService $service;

    protected function setUp(): void
    {
        $settings = new SettingsService(null, [
            'tracing.enabled' => false, // Disabled by default for tests
        ]);
        $settings->initialize();

        $this->service = new TracingService($settings);
        $this->service->initialize();
    }

    public function testGetName(): void
    {
        $this->assertSame('tracing', $this->service->getName());
    }

    public function testStartAndEndTrace(): void
    {
        $this->service->startTrace('trace-123', 'test-trace', [
            'user_id' => 'user-123',
        ]);

        $context = $this->service->getContext('trace-123');
        $this->assertNotNull($context);
        $this->assertSame('trace-123', $context->traceId);
        $this->assertSame('test-trace', $context->traceName);

        $this->service->endTrace('trace-123', ['result' => 'success']);

        $context = $this->service->getContext('trace-123');
        $this->assertNull($context); // Removed after ending
    }

    public function testRecordSpan(): void
    {
        $executed = false;
        $result = $this->service->recordSpan('test-span', function () use (&$executed) {
            $executed = true;
            return 'span-result';
        });

        $this->assertTrue($executed);
        $this->assertSame('span-result', $result);
    }

    public function testRecordSpanWithException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Test error');

        $this->service->recordSpan('failing-span', function () {
            throw new \RuntimeException('Test error');
        });
    }

    public function testRecordMetric(): void
    {
        // Should not throw
        $this->service->recordMetric('test.metric', 123.45, [
            'tag1' => 'value1',
        ]);

        $this->assertTrue(true);
    }

    public function testIsEnabled(): void
    {
        $this->assertFalse($this->service->isEnabled());
    }

    public function testGetContext(): void
    {
        $this->service->startTrace('trace-123', 'test', []);

        $context = $this->service->getContext('trace-123');

        $this->assertNotNull($context);
        $this->assertSame('trace-123', $context->traceId);
    }

    public function testGetNonexistentContext(): void
    {
        $context = $this->service->getContext('nonexistent');

        $this->assertNull($context);
    }
}
