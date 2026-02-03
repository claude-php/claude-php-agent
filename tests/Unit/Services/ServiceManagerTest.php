<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Services;

use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceNotFoundException;
use ClaudeAgents\Services\ServiceType;
use ClaudeAgents\Services\Settings\SettingsServiceFactory;
use ClaudeAgents\Services\Cache\CacheServiceFactory;
use PHPUnit\Framework\TestCase;

class ServiceManagerTest extends TestCase
{
    private ServiceManager $serviceManager;

    protected function setUp(): void
    {
        $this->serviceManager = ServiceManager::getInstance();
        $this->serviceManager->reset();
    }

    protected function tearDown(): void
    {
        $this->serviceManager->reset();
    }

    public function testGetInstanceReturnsSingleton(): void
    {
        $instance1 = ServiceManager::getInstance();
        $instance2 = ServiceManager::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    public function testRegisterFactory(): void
    {
        $factory = new SettingsServiceFactory();
        $this->serviceManager->registerFactory($factory);

        $this->assertTrue($this->serviceManager->has(ServiceType::SETTINGS));
    }

    public function testGetServiceCreatesInstance(): void
    {
        $this->serviceManager->registerFactory(new SettingsServiceFactory());

        $service = $this->serviceManager->get(ServiceType::SETTINGS);

        $this->assertInstanceOf(\ClaudeAgents\Services\Settings\SettingsService::class, $service);
        $this->assertTrue($service->isReady());
    }

    public function testGetServiceReturnsSameInstance(): void
    {
        $this->serviceManager->registerFactory(new SettingsServiceFactory());

        $service1 = $this->serviceManager->get(ServiceType::SETTINGS);
        $service2 = $this->serviceManager->get(ServiceType::SETTINGS);

        $this->assertSame($service1, $service2);
    }

    public function testGetServiceThrowsExceptionWhenNotFound(): void
    {
        $this->expectException(ServiceNotFoundException::class);

        $this->serviceManager->get(ServiceType::CACHE);
    }

    public function testRegisterServiceDirectly(): void
    {
        $this->serviceManager->registerFactory(new SettingsServiceFactory());
        $settings = $this->serviceManager->get(ServiceType::SETTINGS);

        $this->serviceManager->register(ServiceType::SETTINGS, $settings);

        $retrieved = $this->serviceManager->get(ServiceType::SETTINGS);
        $this->assertSame($settings, $retrieved);
    }

    public function testDependencyResolution(): void
    {
        // Cache depends on Settings
        $this->serviceManager
            ->registerFactory(new SettingsServiceFactory())
            ->registerFactory(new CacheServiceFactory());

        $cache = $this->serviceManager->get(ServiceType::CACHE);

        $this->assertInstanceOf(\ClaudeAgents\Services\Cache\CacheService::class, $cache);
        $this->assertTrue($cache->isReady());
    }

    public function testTeardownAll(): void
    {
        $this->serviceManager->registerFactory(new SettingsServiceFactory());
        $service = $this->serviceManager->get(ServiceType::SETTINGS);

        $this->assertTrue($service->isReady());

        $this->serviceManager->teardownAll();

        $this->assertCount(0, $this->serviceManager->getServices());
    }

    public function testReset(): void
    {
        $this->serviceManager->registerFactory(new SettingsServiceFactory());
        $this->serviceManager->get(ServiceType::SETTINGS);

        $this->serviceManager->reset();

        $this->assertCount(0, $this->serviceManager->getServices());
        $this->assertCount(0, $this->serviceManager->getFactories());
    }
}
