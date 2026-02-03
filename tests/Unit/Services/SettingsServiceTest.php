<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Services;

use ClaudeAgents\Services\Settings\SettingsService;
use PHPUnit\Framework\TestCase;

class SettingsServiceTest extends TestCase
{
    private SettingsService $service;

    protected function setUp(): void
    {
        $this->service = new SettingsService();
    }

    public function testGetName(): void
    {
        $this->assertSame('settings', $this->service->getName());
    }

    public function testInitialize(): void
    {
        $this->assertFalse($this->service->isReady());

        $this->service->initialize();

        $this->assertTrue($this->service->isReady());
    }

    public function testGetWithDefault(): void
    {
        $this->service->initialize();

        $value = $this->service->get('nonexistent', 'default');

        $this->assertSame('default', $value);
    }

    public function testSetAndGet(): void
    {
        $this->service->initialize();

        $this->service->set('key', 'value');
        $value = $this->service->get('key');

        $this->assertSame('value', $value);
    }

    public function testDotNotation(): void
    {
        $this->service->initialize();

        $this->service->set('database.host', 'localhost');
        $this->service->set('database.port', 3306);

        $this->assertSame('localhost', $this->service->get('database.host'));
        $this->assertSame(3306, $this->service->get('database.port'));
    }

    public function testHas(): void
    {
        $this->service->initialize();

        $this->service->set('exists', 'value');

        $this->assertTrue($this->service->has('exists'));
        $this->assertFalse($this->service->has('nonexistent'));
    }

    public function testAll(): void
    {
        $this->service->initialize();

        $this->service->set('key1', 'value1');
        $this->service->set('key2', 'value2');

        $all = $this->service->all();

        $this->assertArrayHasKey('key1', $all);
        $this->assertArrayHasKey('key2', $all);
    }

    public function testTeardown(): void
    {
        $this->service->initialize();
        $this->assertTrue($this->service->isReady());

        $this->service->teardown();

        $this->assertFalse($this->service->isReady());
    }
}
