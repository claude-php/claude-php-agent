<?php

declare(strict_types=1);

namespace ClaudeAgents\Services;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Central service manager with automatic dependency injection.
 *
 * Manages the lifecycle of all services in the application, providing:
 * - Lazy initialization
 * - Automatic dependency resolution
 * - Service replacement for testing
 * - Lifecycle management (initialize, teardown)
 */
class ServiceManager
{
    private static ?self $instance = null;

    /**
     * @var array<string, ServiceInterface> Registered service instances
     */
    private array $services = [];

    /**
     * @var array<string, ServiceFactory> Registered service factories
     */
    private array $factories = [];

    /**
     * @var array<string, bool> Track which services are being created (prevent circular deps)
     */
    private array $creating = [];

    /**
     * @var LoggerInterface PSR-3 logger
     */
    private LoggerInterface $logger;

    /**
     * Private constructor for singleton pattern.
     */
    private function __construct()
    {
        $this->logger = new NullLogger();
    }

    /**
     * Get the singleton instance of the service manager.
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Set the PSR-3 logger for the service manager.
     *
     * @param LoggerInterface $logger
     * @return self
     */
    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * Register a service factory.
     *
     * @param ServiceFactory $factory The factory to register
     * @return self
     */
    public function registerFactory(ServiceFactory $factory): self
    {
        $serviceType = $factory->getServiceType();
        $this->factories[$serviceType->value] = $factory;

        $this->logger->debug("Registered factory for service: {$serviceType->value}");

        return $this;
    }

    /**
     * Register a service instance directly.
     *
     * This bypasses the factory system and registers a service instance directly.
     * Useful for testing or when you have a pre-configured service.
     *
     * @param ServiceType $type The service type
     * @param ServiceInterface $service The service instance
     * @return self
     */
    public function register(ServiceType $type, ServiceInterface $service): self
    {
        $this->services[$type->value] = $service;

        // Initialize if not already ready
        if (! $service->isReady()) {
            $service->initialize();
        }

        $this->logger->debug("Registered service instance: {$type->value}");

        return $this;
    }

    /**
     * Get a service by its type.
     *
     * If the service doesn't exist yet, it will be created automatically
     * using its factory and all dependencies will be resolved.
     *
     * @param ServiceType $type The service type to get
     * @return ServiceInterface The service instance
     * @throws ServiceNotFoundException If service or factory not found
     * @throws \RuntimeException If service creation fails
     */
    public function get(ServiceType $type): ServiceInterface
    {
        $key = $type->value;

        // Return existing service
        if (isset($this->services[$key])) {
            return $this->services[$key];
        }

        // Create new service
        return $this->createService($type);
    }

    /**
     * Check if a service is registered.
     *
     * @param ServiceType $type The service type to check
     * @return bool True if the service exists or has a factory
     */
    public function has(ServiceType $type): bool
    {
        $key = $type->value;

        return isset($this->services[$key]) || isset($this->factories[$key]);
    }

    /**
     * Mock a service for testing.
     *
     * Replaces an existing service with a mock implementation.
     * If the service exists, it will be torn down first.
     *
     * @param ServiceType $type The service type to mock
     * @param ServiceInterface $mock The mock service instance
     * @return self
     */
    public function mock(ServiceType $type, ServiceInterface $mock): self
    {
        $key = $type->value;

        // Teardown existing service
        if (isset($this->services[$key])) {
            $this->services[$key]->teardown();
        }

        return $this->register($type, $mock);
    }

    /**
     * Teardown all services.
     *
     * Calls teardown() on all registered services in reverse order of creation.
     * Useful for graceful application shutdown.
     *
     * @return void
     */
    public function teardownAll(): void
    {
        $services = array_reverse($this->services);

        foreach ($services as $key => $service) {
            try {
                $this->logger->debug("Tearing down service: {$key}");
                $service->teardown();
            } catch (\Throwable $e) {
                $this->logger->error("Error tearing down service {$key}: {$e->getMessage()}");
            }
        }

        $this->services = [];
        $this->logger->debug('All services torn down');
    }

    /**
     * Reset the service manager (for testing).
     *
     * Tears down all services and clears factories.
     *
     * @return void
     */
    public function reset(): void
    {
        $this->teardownAll();
        $this->factories = [];
        $this->creating = [];
        $this->logger->debug('Service manager reset');
    }

    /**
     * Create a service instance using its factory.
     *
     * @param ServiceType $type The service type to create
     * @return ServiceInterface The created service
     * @throws ServiceNotFoundException If factory not found
     * @throws \RuntimeException If circular dependency detected or creation fails
     */
    private function createService(ServiceType $type): ServiceInterface
    {
        $key = $type->value;

        // Check for circular dependencies
        if (isset($this->creating[$key])) {
            throw new \RuntimeException("Circular dependency detected while creating service: {$key}");
        }

        // Check factory exists
        if (! isset($this->factories[$key])) {
            throw new ServiceNotFoundException("No factory registered for service: {$key}");
        }

        $this->creating[$key] = true;

        try {
            $factory = $this->factories[$key];

            // Resolve dependencies
            $dependencies = [];
            foreach ($factory->getDependencies() as $dependencyType) {
                $dependencies[$dependencyType->value] = $this->get($dependencyType);
            }

            // Create service
            $this->logger->debug("Creating service: {$key}");
            $service = $factory->create($dependencies);

            // Initialize service
            if (! $service->isReady()) {
                $service->initialize();
            }

            // Store service
            $this->services[$key] = $service;

            $this->logger->debug("Service created and initialized: {$key}");

            return $service;
        } finally {
            unset($this->creating[$key]);
        }
    }

    /**
     * Get all registered services.
     *
     * @return array<string, ServiceInterface>
     */
    public function getServices(): array
    {
        return $this->services;
    }

    /**
     * Get all registered factories.
     *
     * @return array<string, ServiceFactory>
     */
    public function getFactories(): array
    {
        return $this->factories;
    }

    /**
     * Prevent cloning of the singleton instance.
     */
    private function __clone()
    {
    }

    /**
     * Prevent unserialization of the singleton instance.
     */
    public function __wakeup(): void
    {
        throw new \RuntimeException('Cannot unserialize singleton');
    }
}
