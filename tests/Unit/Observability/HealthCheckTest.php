<?php

declare(strict_types=1);

namespace Tests\Unit\Observability;

use ClaudeAgents\Observability\HealthCheck;
use PHPUnit\Framework\TestCase;

class HealthCheckTest extends TestCase
{
    public function testRegisterAndRunCheck(): void
    {
        $health = new HealthCheck();

        $health->registerCheck('test', function () {
            return [
                'status' => HealthCheck::STATUS_HEALTHY,
                'message' => 'All good',
            ];
        });

        $result = $health->check();

        $this->assertEquals(HealthCheck::STATUS_HEALTHY, $result['status']);
        $this->assertArrayHasKey('test', $result['checks']);
        $this->assertEquals(HealthCheck::STATUS_HEALTHY, $result['checks']['test']['status']);
    }

    public function testUnhealthyCheckAffectsOverallStatus(): void
    {
        $health = new HealthCheck();

        $health->registerCheck('good', function () {
            return ['status' => HealthCheck::STATUS_HEALTHY, 'message' => 'OK'];
        });

        $health->registerCheck('bad', function () {
            return ['status' => HealthCheck::STATUS_UNHEALTHY, 'message' => 'Failed'];
        });

        $result = $health->check();

        $this->assertEquals(HealthCheck::STATUS_UNHEALTHY, $result['status']);
    }

    public function testDegradedStatus(): void
    {
        $health = new HealthCheck();

        $health->registerCheck('degraded', function () {
            return ['status' => HealthCheck::STATUS_DEGRADED, 'message' => 'Slow'];
        });

        $result = $health->check();

        $this->assertEquals(HealthCheck::STATUS_DEGRADED, $result['status']);
    }

    public function testCheckException(): void
    {
        $health = new HealthCheck();

        $health->registerCheck('failing', function () {
            throw new \Exception('Check failed');
        });

        $result = $health->check();

        $this->assertEquals(HealthCheck::STATUS_UNHEALTHY, $result['status']);
        $this->assertStringContainsString('Check failed', $result['checks']['failing']['message']);
    }

    public function testCaching(): void
    {
        $health = new HealthCheck(cacheTtl: 10);
        $callCount = 0;

        $health->registerCheck('cached', function () use (&$callCount) {
            $callCount++;

            return ['status' => HealthCheck::STATUS_HEALTHY, 'message' => 'OK'];
        });

        $health->check();
        $health->check();

        // Should only be called once due to caching
        $this->assertEquals(1, $callCount);

        // Clear cache and check again
        $health->clearCache();
        $health->check();

        $this->assertEquals(2, $callCount);
    }

    public function testDefaultHealthChecks(): void
    {
        $health = HealthCheck::createDefault();

        $checks = $health->getRegisteredChecks();

        $this->assertContains('php_memory', $checks);
        $this->assertContains('disk_space', $checks);
        $this->assertContains('php_version', $checks);
    }

    public function testIsHealthy(): void
    {
        $health = new HealthCheck();

        $health->registerCheck('test', function () {
            return ['status' => HealthCheck::STATUS_HEALTHY, 'message' => 'OK'];
        });

        $this->assertTrue($health->isHealthy());
    }
}
