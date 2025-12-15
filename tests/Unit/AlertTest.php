<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit;

use ClaudeAgents\Monitoring\Alert;
use ClaudeAgents\Monitoring\Metric;
use PHPUnit\Framework\TestCase;

class AlertTest extends TestCase
{
    public function testConstructorWithRequiredFields(): void
    {
        $alert = new Alert(
            title: 'Test Alert',
            message: 'This is a test alert'
        );

        $this->assertEquals('Test Alert', $alert->getTitle());
        $this->assertEquals('This is a test alert', $alert->getMessage());
        $this->assertEquals(Alert::SEVERITY_INFO, $alert->getSeverity());
        $this->assertNull($alert->getMetric());
        $this->assertEmpty($alert->getContext());
        $this->assertIsString($alert->getId());
        $this->assertStringStartsWith('alert_', $alert->getId());
    }

    public function testConstructorWithAllFields(): void
    {
        $metric = new Metric('cpu_usage', 95.5);
        $context = ['host' => 'web-01', 'threshold' => 90];

        $alert = new Alert(
            title: 'High CPU',
            message: 'CPU usage is critical',
            severity: Alert::SEVERITY_CRITICAL,
            metric: $metric,
            context: $context
        );

        $this->assertEquals('High CPU', $alert->getTitle());
        $this->assertEquals('CPU usage is critical', $alert->getMessage());
        $this->assertEquals(Alert::SEVERITY_CRITICAL, $alert->getSeverity());
        $this->assertSame($metric, $alert->getMetric());
        $this->assertEquals($context, $alert->getContext());
    }

    public function testGetId(): void
    {
        $alert1 = new Alert('Test 1', 'Message 1');
        $alert2 = new Alert('Test 2', 'Message 2');

        $this->assertNotEquals($alert1->getId(), $alert2->getId());
    }

    public function testGetTimestamp(): void
    {
        $before = microtime(true);
        $alert = new Alert('Test', 'Message');
        $after = microtime(true);

        $timestamp = $alert->getTimestamp();
        $this->assertGreaterThanOrEqual($before, $timestamp);
        $this->assertLessThanOrEqual($after, $timestamp);
    }

    public function testSeverityConstants(): void
    {
        $this->assertEquals('info', Alert::SEVERITY_INFO);
        $this->assertEquals('warning', Alert::SEVERITY_WARNING);
        $this->assertEquals('error', Alert::SEVERITY_ERROR);
        $this->assertEquals('critical', Alert::SEVERITY_CRITICAL);
    }

    public function testIsCritical(): void
    {
        $criticalAlert = new Alert('Critical', 'Message', Alert::SEVERITY_CRITICAL);
        $warningAlert = new Alert('Warning', 'Message', Alert::SEVERITY_WARNING);
        $errorAlert = new Alert('Error', 'Message', Alert::SEVERITY_ERROR);
        $infoAlert = new Alert('Info', 'Message', Alert::SEVERITY_INFO);

        $this->assertTrue($criticalAlert->isCritical());
        $this->assertFalse($warningAlert->isCritical());
        $this->assertFalse($errorAlert->isCritical());
        $this->assertFalse($infoAlert->isCritical());
    }

    public function testToArray(): void
    {
        $metric = new Metric('cpu_usage', 95.5);
        $context = ['host' => 'web-01'];

        $alert = new Alert(
            title: 'High CPU',
            message: 'CPU usage critical',
            severity: Alert::SEVERITY_WARNING,
            metric: $metric,
            context: $context
        );

        $array = $alert->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('id', $array);
        $this->assertEquals('High CPU', $array['title']);
        $this->assertEquals('CPU usage critical', $array['message']);
        $this->assertEquals(Alert::SEVERITY_WARNING, $array['severity']);
        $this->assertIsArray($array['metric']);
        $this->assertEquals('cpu_usage', $array['metric']['name']);
        $this->assertEquals($context, $array['context']);
        $this->assertIsFloat($array['timestamp']);
    }

    public function testToArrayWithoutMetric(): void
    {
        $alert = new Alert('Test', 'Message');
        $array = $alert->toArray();

        $this->assertNull($array['metric']);
    }

    public function testWithInfoSeverity(): void
    {
        $alert = new Alert('Info', 'Message', Alert::SEVERITY_INFO);

        $this->assertEquals(Alert::SEVERITY_INFO, $alert->getSeverity());
        $this->assertFalse($alert->isCritical());
    }

    public function testWithWarningSeverity(): void
    {
        $alert = new Alert('Warning', 'Message', Alert::SEVERITY_WARNING);

        $this->assertEquals(Alert::SEVERITY_WARNING, $alert->getSeverity());
        $this->assertFalse($alert->isCritical());
    }

    public function testWithErrorSeverity(): void
    {
        $alert = new Alert('Error', 'Message', Alert::SEVERITY_ERROR);

        $this->assertEquals(Alert::SEVERITY_ERROR, $alert->getSeverity());
        $this->assertFalse($alert->isCritical());
    }

    public function testWithCriticalSeverity(): void
    {
        $alert = new Alert('Critical', 'Message', Alert::SEVERITY_CRITICAL);

        $this->assertEquals(Alert::SEVERITY_CRITICAL, $alert->getSeverity());
        $this->assertTrue($alert->isCritical());
    }

    public function testContextIsOptional(): void
    {
        $alert = new Alert('Test', 'Message');

        $this->assertIsArray($alert->getContext());
        $this->assertEmpty($alert->getContext());
    }

    public function testContextWithMultipleValues(): void
    {
        $context = [
            'host' => 'web-01',
            'region' => 'us-east',
            'threshold' => 90,
            'duration' => '5 minutes',
            'tags' => ['production', 'critical'],
        ];

        $alert = new Alert('Test', 'Message', context: $context);

        $this->assertEquals($context, $alert->getContext());
    }

    public function testMetricIntegration(): void
    {
        $metric = new Metric('memory_usage', 92.5, [
            'host' => 'db-01',
            'pool' => 'cache',
        ]);

        $alert = new Alert(
            'High Memory Usage',
            'Memory usage exceeded threshold',
            Alert::SEVERITY_WARNING,
            $metric
        );

        $this->assertSame($metric, $alert->getMetric());
        $this->assertEquals('memory_usage', $alert->getMetric()->getName());
        $this->assertEquals(92.5, $alert->getMetric()->getValue());
    }

    public function testAlertIdIsUnique(): void
    {
        $ids = [];

        for ($i = 0; $i < 100; $i++) {
            $alert = new Alert("Alert {$i}", 'Message');
            $ids[] = $alert->getId();
        }

        // All IDs should be unique
        $this->assertEquals(count($ids), count(array_unique($ids)));
    }
}
