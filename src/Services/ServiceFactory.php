<?php

declare(strict_types=1);

namespace ClaudeAgents\Services;

use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;

/**
 * Abstract factory for service creation with automatic dependency resolution.
 *
 * Factories introspect service constructors to determine dependencies
 * and automatically resolve them from the ServiceManager.
 */
abstract class ServiceFactory
{
    /**
     * @var ServiceType The type of service this factory creates
     */
    protected ServiceType $serviceType;

    /**
     * @var class-string<ServiceInterface> The service class to instantiate
     */
    protected string $serviceClass;

    /**
     * @var array<ServiceType> Cached list of dependencies
     */
    private ?array $dependencies = null;

    /**
     * Create the service instance.
     *
     * @param array<string, ServiceInterface> $dependencies Resolved service dependencies
     * @return ServiceInterface The created service
     * @throws \RuntimeException If service creation fails
     */
    abstract public function create(array $dependencies = []): ServiceInterface;

    /**
     * Get the service type this factory creates.
     *
     * @return ServiceType
     */
    public function getServiceType(): ServiceType
    {
        return $this->serviceType;
    }

    /**
     * Get the service class this factory creates.
     *
     * @return class-string<ServiceInterface>
     */
    public function getServiceClass(): string
    {
        return $this->serviceClass;
    }

    /**
     * Get the dependencies required by this service.
     *
     * Uses reflection to inspect the service constructor and determine
     * which other services are needed.
     *
     * @return array<ServiceType> Array of required service types
     */
    public function getDependencies(): array
    {
        if ($this->dependencies !== null) {
            return $this->dependencies;
        }

        $this->dependencies = [];

        try {
            $reflection = new ReflectionClass($this->serviceClass);
            $constructor = $reflection->getConstructor();

            if ($constructor === null) {
                return $this->dependencies;
            }

            foreach ($constructor->getParameters() as $parameter) {
                $type = $parameter->getType();

                if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                    continue;
                }

                $typeName = $type->getName();

                // Check if the type is a service interface
                if (! is_subclass_of($typeName, ServiceInterface::class)) {
                    continue;
                }

                // Find matching ServiceType
                $serviceType = $this->findServiceTypeForClass($typeName);
                if ($serviceType !== null) {
                    $this->dependencies[] = $serviceType;
                }
            }
        } catch (ReflectionException $e) {
            // If reflection fails, return empty dependencies
        }

        return $this->dependencies;
    }

    /**
     * Find the ServiceType enum for a given class name.
     *
     * @param string $className Full class name
     * @return ServiceType|null Matching service type or null
     */
    private function findServiceTypeForClass(string $className): ?ServiceType
    {
        foreach (ServiceType::cases() as $type) {
            if ($type->getClassName() === $className) {
                return $type;
            }
        }

        return null;
    }

    /**
     * Create service with automatic dependency injection.
     *
     * Helper method that uses reflection to inject dependencies into
     * the service constructor.
     *
     * @param array<string, ServiceInterface> $dependencies
     * @return ServiceInterface
     * @throws \RuntimeException
     */
    protected function createWithDependencies(array $dependencies): ServiceInterface
    {
        try {
            $reflection = new ReflectionClass($this->serviceClass);
            $constructor = $reflection->getConstructor();

            if ($constructor === null) {
                return new $this->serviceClass();
            }

            $args = [];
            foreach ($constructor->getParameters() as $parameter) {
                $type = $parameter->getType();

                if (! $type instanceof ReflectionNamedType) {
                    if ($parameter->isDefaultValueAvailable()) {
                        $args[] = $parameter->getDefaultValue();
                        continue;
                    }
                    throw new \RuntimeException(
                        "Cannot resolve parameter '{$parameter->getName()}' for {$this->serviceClass}"
                    );
                }

                $typeName = $type->getName();

                // If it's a service, inject it
                if (is_subclass_of($typeName, ServiceInterface::class)) {
                    $serviceType = $this->findServiceTypeForClass($typeName);
                    if ($serviceType !== null && isset($dependencies[$serviceType->value])) {
                        $args[] = $dependencies[$serviceType->value];
                        continue;
                    }
                }

                // Try to use default value
                if ($parameter->isDefaultValueAvailable()) {
                    $args[] = $parameter->getDefaultValue();
                    continue;
                }

                // If nullable, use null
                if ($type->allowsNull()) {
                    $args[] = null;
                    continue;
                }

                throw new \RuntimeException(
                    "Cannot resolve parameter '{$parameter->getName()}' for {$this->serviceClass}"
                );
            }

            return $reflection->newInstanceArgs($args);
        } catch (ReflectionException $e) {
            throw new \RuntimeException(
                "Failed to create service {$this->serviceClass}: {$e->getMessage()}",
                0,
                $e
            );
        }
    }
}
