<?php

declare(strict_types=1);

namespace ClaudeAgents\Services;

/**
 * Base interface that all services must implement.
 *
 * Services provide reusable functionality across the agent framework,
 * with lifecycle management and dependency injection support.
 */
interface ServiceInterface
{
    /**
     * Get the unique name of this service.
     *
     * @return string Service name (e.g., 'cache', 'storage', 'tracing')
     */
    public function getName(): string;

    /**
     * Initialize the service.
     *
     * Called by the ServiceManager when the service is first created.
     * Use this to set up connections, load configuration, etc.
     *
     * @return void
     * @throws \RuntimeException If initialization fails
     */
    public function initialize(): void;

    /**
     * Teardown the service and release resources.
     *
     * Called when the application is shutting down or when the service
     * is being replaced. Close connections, flush buffers, etc.
     *
     * @return void
     */
    public function teardown(): void;

    /**
     * Check if the service is ready to use.
     *
     * @return bool True if the service is initialized and ready
     */
    public function isReady(): bool;

    /**
     * Get the service schema for runtime introspection.
     *
     * Returns metadata about the service including available methods,
     * their parameters, return types, and documentation.
     *
     * @return array<string, mixed> Service schema
     */
    public function getSchema(): array;
}
