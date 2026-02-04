<?php

declare(strict_types=1);

namespace ClaudeAgents\Services\ErrorHandling;

use ClaudeAgents\Services\ServiceFactory;
use ClaudeAgents\Services\ServiceInterface;
use ClaudeAgents\Services\ServiceType;
use Psr\Log\NullLogger;

/**
 * Factory for creating ErrorHandlingService instances.
 *
 * Handles dependency injection and configuration loading.
 */
class ErrorHandlingServiceFactory extends ServiceFactory
{
    protected ServiceType $serviceType = ServiceType::ERROR_HANDLING;
    protected string $serviceClass = ErrorHandlingService::class;

    /**
     * Create an ErrorHandlingService instance.
     *
     * @param array<string, ServiceInterface> $dependencies Resolved service dependencies
     * @return ServiceInterface Configured error handling service
     */
    public function create(array $dependencies = []): ServiceInterface
    {
        // Create service with default configuration
        $service = new ErrorHandlingService(
            logger: new NullLogger(),
            maxRetries: 3,
            initialDelayMs: 1000,
            customPatterns: []
        );

        $service->initialize();

        return $service;
    }
}
