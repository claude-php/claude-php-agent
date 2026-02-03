<?php

declare(strict_types=1);

namespace ClaudeAgents\Services\Tracing;

use ClaudeAgents\Services\ServiceFactory;
use ClaudeAgents\Services\ServiceInterface;
use ClaudeAgents\Services\ServiceType;

/**
 * Factory for creating TracingService instances.
 */
class TracingServiceFactory extends ServiceFactory
{
    protected ServiceType $serviceType = ServiceType::TRACING;
    protected string $serviceClass = TracingService::class;

    public function create(array $dependencies = []): ServiceInterface
    {
        return $this->createWithDependencies($dependencies);
    }
}
