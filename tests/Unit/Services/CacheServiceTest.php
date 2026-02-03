<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Services;

use ClaudeAgents\Cache\ArrayCache;
use ClaudeAgents\Services\Cache\CacheService;
use ClaudeAgents\Services\Settings\SettingsService;
use PHPUnit\Framework\TestCase;

class CacheServiceTest extends TestCase
{
    private CacheService $service;

    protected function setUp(): void
    {
        $settings = new SettingsService();
        $settings->initialize();

        $this->service = new CacheService($settings, new ArrayCache());
        $this->service->initialize();
    }

    public function testGetName(): void
    {
        $this->assertSame('cache', $this->service->getName());
    }

    public function testSetAndGet(): void
    {
        $this->service->set('key', 'value');
        $value = $this->service->get('key');

        $this->assertSame('value', $value);
    }

    public function testGetNonexistent(): void
    {
        $value = $this->service->get('nonexistent');

        $this->assertNull($value);
    }

    public function testHas(): void
    {
        $this->service->set('exists', 'value');

        $this->assertTrue($this->service->has('exists'));
        $this->assertFalse($this->service->has('nonexistent'));
    }

    public function testDelete(): void
    {
        $this->service->set('key', 'value');
        $this->assertTrue($this->service->has('key'));

        $this->service->delete('key');

        $this->assertFalse($this->service->has('key'));
    }

    public function testRemember(): void
    {
        $callCount = 0;
        $callback = function () use (&$callCount) {
            $callCount++;
            return 'computed';
        };

        // First call should execute callback
        $result1 = $this->service->remember('key', $callback);
        $this->assertSame('computed', $result1);
        $this->assertSame(1, $callCount);

        // Second call should use cache
        $result2 = $this->service->remember('key', $callback);
        $this->assertSame('computed', $result2);
        $this->assertSame(1, $callCount); // Not incremented
    }

    public function testNamespace(): void
    {
        $this->service->setNamespace('test');

        $this->service->set('key', 'value');
        $this->assertTrue($this->service->has('key'));

        // Different namespace
        $this->service->setNamespace('other');
        $this->assertFalse($this->service->has('key'));
    }

    public function testClear(): void
    {
        $this->service->set('key1', 'value1');
        $this->service->set('key2', 'value2');

        $this->service->clear();

        $this->assertFalse($this->service->has('key1'));
        $this->assertFalse($this->service->has('key2'));
    }
}
