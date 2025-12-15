<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit;

use ClaudeAgents\Monitoring\Metric;
use PHPUnit\Framework\TestCase;

class MetricTest extends TestCase
{
    public function testConstructor(): void
    {
        $metric = new Metric('cpu_usage', 75.5);

        $this->assertEquals('cpu_usage', $metric->getName());
        $this->assertEquals(75.5, $metric->getValue());
        $this->assertEmpty($metric->getMetadata());
        $this->assertIsFloat($metric->getTimestamp());
    }

    public function testConstructorWithMetadata(): void
    {
        $metadata = ['host' => 'web-01', 'region' => 'us-east'];
        $metric = new Metric('memory_usage', 85, $metadata);

        $this->assertEquals('memory_usage', $metric->getName());
        $this->assertEquals(85, $metric->getValue());
        $this->assertEquals($metadata, $metric->getMetadata());
    }

    public function testGetTimestamp(): void
    {
        $before = microtime(true);
        $metric = new Metric('test', 100);
        $after = microtime(true);

        $timestamp = $metric->getTimestamp();
        $this->assertGreaterThanOrEqual($before, $timestamp);
        $this->assertLessThanOrEqual($after, $timestamp);
    }

    public function testExceedsThresholdGreaterThan(): void
    {
        $metric = new Metric('cpu', 85);

        $this->assertTrue($metric->exceedsThreshold(80, '>'));
        $this->assertFalse($metric->exceedsThreshold(90, '>'));
        $this->assertFalse($metric->exceedsThreshold(85, '>'));
    }

    public function testExceedsThresholdGreaterThanOrEqual(): void
    {
        $metric = new Metric('cpu', 85);

        $this->assertTrue($metric->exceedsThreshold(85, '>='));
        $this->assertTrue($metric->exceedsThreshold(80, '>='));
        $this->assertFalse($metric->exceedsThreshold(90, '>='));
    }

    public function testExceedsThresholdLessThan(): void
    {
        $metric = new Metric('cpu', 45);

        $this->assertTrue($metric->exceedsThreshold(50, '<'));
        $this->assertFalse($metric->exceedsThreshold(40, '<'));
        $this->assertFalse($metric->exceedsThreshold(45, '<'));
    }

    public function testExceedsThresholdLessThanOrEqual(): void
    {
        $metric = new Metric('cpu', 45);

        $this->assertTrue($metric->exceedsThreshold(45, '<='));
        $this->assertTrue($metric->exceedsThreshold(50, '<='));
        $this->assertFalse($metric->exceedsThreshold(40, '<='));
    }

    public function testExceedsThresholdEqual(): void
    {
        $metric = new Metric('cpu', 50);

        $this->assertTrue($metric->exceedsThreshold(50, '=='));
        $this->assertFalse($metric->exceedsThreshold(51, '=='));
    }

    public function testExceedsThresholdNotEqual(): void
    {
        $metric = new Metric('cpu', 50);

        $this->assertTrue($metric->exceedsThreshold(51, '!='));
        $this->assertFalse($metric->exceedsThreshold(50, '!='));
    }

    public function testExceedsThresholdDefaultOperator(): void
    {
        $metric = new Metric('cpu', 85);

        // Default is '>'
        $this->assertTrue($metric->exceedsThreshold(80));
        $this->assertFalse($metric->exceedsThreshold(90));
    }

    public function testExceedsThresholdInvalidOperator(): void
    {
        $metric = new Metric('cpu', 85);

        $this->assertFalse($metric->exceedsThreshold(80, 'invalid'));
    }

    public function testExceedsThresholdNonNumericValue(): void
    {
        $metric = new Metric('status', 'online');

        $this->assertFalse($metric->exceedsThreshold(50, '>'));
        $this->assertFalse($metric->exceedsThreshold(50, '<'));
    }

    public function testToArray(): void
    {
        $metadata = ['host' => 'web-01'];
        $metric = new Metric('cpu_usage', 75.5, $metadata);

        $array = $metric->toArray();

        $this->assertIsArray($array);
        $this->assertEquals('cpu_usage', $array['name']);
        $this->assertEquals(75.5, $array['value']);
        $this->assertEquals($metadata, $array['metadata']);
        $this->assertIsFloat($array['timestamp']);
    }

    public function testWithStringValue(): void
    {
        $metric = new Metric('status', 'healthy');

        $this->assertEquals('status', $metric->getName());
        $this->assertEquals('healthy', $metric->getValue());
    }

    public function testWithArrayValue(): void
    {
        $value = ['cpu' => 50, 'memory' => 70];
        $metric = new Metric('system_stats', $value);

        $this->assertEquals('system_stats', $metric->getName());
        $this->assertEquals($value, $metric->getValue());
    }

    public function testWithNullValue(): void
    {
        $metric = new Metric('nullable_metric', null);

        $this->assertEquals('nullable_metric', $metric->getName());
        $this->assertNull($metric->getValue());
    }

    public function testMetadataImmutability(): void
    {
        $metadata = ['host' => 'web-01'];
        $metric = new Metric('cpu', 50, $metadata);

        // Modify original array
        $metadata['host'] = 'web-02';

        // Metric should still have original value
        $this->assertEquals('web-01', $metric->getMetadata()['host']);
    }
}
