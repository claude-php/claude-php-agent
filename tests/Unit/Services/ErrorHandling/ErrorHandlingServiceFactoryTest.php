<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Services\ErrorHandling;

use ClaudeAgents\Services\ErrorHandling\ErrorHandlingService;
use ClaudeAgents\Services\ErrorHandling\ErrorHandlingServiceFactory;
use ClaudeAgents\Services\ServiceType;
use PHPUnit\Framework\TestCase;

class ErrorHandlingServiceFactoryTest extends TestCase
{
    private ErrorHandlingServiceFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new ErrorHandlingServiceFactory();
    }

    public function testCreateReturnsErrorHandlingService(): void
    {
        $service = $this->factory->create();

        $this->assertInstanceOf(ErrorHandlingService::class, $service);
        $this->assertTrue($service->isReady());
    }

    public function testServiceType(): void
    {
        $this->assertSame(ServiceType::ERROR_HANDLING, $this->factory->getServiceType());
    }

    public function testServiceClass(): void
    {
        $this->assertSame(ErrorHandlingService::class, $this->factory->getServiceClass());
    }

    public function testServiceName(): void
    {
        $service = $this->factory->create();

        $this->assertSame('error_handling', $service->getName());
    }

    public function testCreatedServiceIsReady(): void
    {
        $service = $this->factory->create();

        $this->assertTrue($service->isReady());
    }

    public function testCreatedServiceHasDefaultPatterns(): void
    {
        $service = $this->factory->create();
        $patterns = $service->getErrorPatterns();

        $this->assertArrayHasKey('rate_limit', $patterns);
        $this->assertArrayHasKey('authentication', $patterns);
        $this->assertArrayHasKey('connection', $patterns);
        $this->assertArrayHasKey('timeout', $patterns);
    }
}
