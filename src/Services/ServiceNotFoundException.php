<?php

declare(strict_types=1);

namespace ClaudeAgents\Services;

/**
 * Exception thrown when a requested service is not found.
 */
class ServiceNotFoundException extends \RuntimeException
{
    /**
     * Create a new ServiceNotFoundException.
     *
     * @param string $message Exception message
     * @param int $code Exception code
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create exception for a missing service.
     *
     * @param string $serviceName The name of the missing service
     * @return self
     */
    public static function forService(string $serviceName): self
    {
        return new self("Service not found: {$serviceName}");
    }

    /**
     * Create exception for a missing factory.
     *
     * @param string $serviceName The name of the service with missing factory
     * @return self
     */
    public static function forFactory(string $serviceName): self
    {
        return new self("No factory registered for service: {$serviceName}");
    }
}
